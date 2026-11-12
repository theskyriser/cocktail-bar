# Domain Layer

> **Names in the examples (`UUID`, `Order`, `Wallet`, `Balance`, `OrderPlacedEvent`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

The domain layer holds the business model: **entities**, **value objects**, **domain events**, and occasionally **domain services** for logic that doesn't belong to a single entity. It is the only place that encodes *rules that must always be true* for the business to make sense.

Key property: **the domain layer is deterministic and pure.** No I/O, no `process.env`, no `new Date()` hidden inside business methods (accept a timestamp argument instead), no randomness other than inside dedicated factory methods that make it explicit.

## Value objects

Small, immutable, identity-less, validated at construction.

Example shape — a `UUID` value object at `domain/UUID.ts`:

```ts
export default class UUID {
    private value: string;

    constructor(uuid: string) {
        if (!uuid || !uuid.match(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)) {
            throw new Error("Invalid uuid");
        }
        this.value = uuid;
    }

    static create() {
        return new UUID(crypto.randomUUID());
    }

    getValue() {
        return this.value;
    }
}
```

Rules:

- **Validate in the constructor.** If the input violates the invariant, throw `Error("<semantic message>")` immediately. Never allow an invalid value object to exist.
- **No setters.** Fields are private and there is no way to mutate after construction.
- **Equality is by value.** If you need equality, implement an `equals(other: T): boolean` method.
- **Static factory for creation.** `UUID.create()` generates a new one; the constructor takes an existing string. The pattern — `create` for fresh instances, constructor for reconstitution — should be consistent across the domain.
- **Expose value via a getter** (`getValue()`), not a public field.

Common VO candidates in a service: `Email`, `Money`, `Cpf`, `PostalCode`, `Percentage`, `PhoneNumber`. Any primitive whose raw value would be meaningless without validation is a VO.

## Entities

Objects with identity (an ID that persists across state changes) and mutable state.

Example shape — an `Order` entity at `domain/Order.ts`:

```ts
export default class Order {
    private orderId: UUID;
    private accountId: UUID;
    // ... other private fields

    constructor(
        orderId: string,
        accountId: string,
        side: string,
        quantity: number,
        price: number,
        fillQuantity: number,
        fillPrice: number,
        status: string,
        timestamp: Date,
    ) {
        this.orderId = new UUID(orderId);
        this.accountId = new UUID(accountId);
        // ...
    }

    static createOrder(accountId: string, side: string, quantity: number, price: number) {
        const orderId = UUID.create().getValue();
        return new Order(orderId, accountId, side, quantity, price, 0, 0, "open", new Date());
    }

    fill(quantity: number, price: number) {
        this.fillPrice = ((this.fillQuantity * this.fillPrice) + (quantity * price)) / (this.fillQuantity + quantity);
        this.fillQuantity += quantity;
        if (this.getAvailableQuantity() === 0) this.status = "closed";
    }

    getAvailableQuantity() {
        return this.quantity - this.fillQuantity;
    }

    // getters for every persisted field
    getOrderId() { return this.orderId.getValue(); }
    getStatus() { return this.status; }
    // ...
}
```

Rules:

- **Private fields + explicit getters.** Never expose raw state publicly.
- **Two construction paths:**
  - `constructor(...)` — accepts every persisted field. Used by repositories to **reconstitute** an entity from a database row.
  - `static createXxx(...)` — used by use cases to **create a new** instance. It fills in defaults (`status = "open"`, `fillQuantity = 0`, `timestamp = new Date()`) and generates any IDs.
- **Behavior is methods, not field mutations.** `order.fill(q, p)` is allowed; consumers setting `order.fillQuantity = ...` is forbidden (private field enforces this).
- **Enforce invariants inside methods.** If `fill` could produce an invalid state, it must throw before mutating.
- **Wrap primitives in value objects** where the primitive has a constraint (`UUID`, `Email`), even if the raw string is what gets persisted.

## Aggregates

An aggregate is an entity that owns a cluster of related objects and enforces their combined invariants. The root is the only entry point from outside the aggregate.

Example — a `Wallet` aggregate at `domain/Wallet.ts` that owns a `Balance[]`:

```ts
export default class Wallet {
    private accountId: UUID;

    constructor(accountId: string, readonly balances: Balance[]) {
        this.accountId = new UUID(accountId);
    }

    static createWallet() {
        return new Wallet(UUID.create().getValue(), []);
    }

    withdraw(assetId: string, quantity: number) {
        const balance = this.balances.find(b => b.assetId === assetId);
        if (!balance) throw new Error("Insufficient funds");
        const newQuantity = balance.quantity - quantity;
        if (newQuantity < 0) throw new Error("Insufficient funds");
        balance.quantity = newQuantity;
    }

    blockOrder(order: Order) {
        const balance = this.balances.find(b => b.assetId === order.getPaymentAsset());
        if (!balance) return false;
        if (balance.getAvailableQuantity() < order.getRequiredQuantity()) return false;
        balance.blockedQuantity += order.getRequiredQuantity();
        return true;
    }
}
```

Rules:

- Mutations to aggregate members **must go through the root**. Code outside the `Wallet` should not push to `balances` directly.
- The root enforces cross-member invariants (`withdraw` checks that the specific balance has enough; `blockOrder` checks available quantity across the balance).
- Aggregate members can be entities (they have identity within the aggregate) or value objects. In this example `Balance` is an entity because it has mutable state.

## Validation vs. invariants — what throws vs. what returns

Two checks can look identical yet belong in different places. **Input validation** filters untrusted data arriving from outside (HTTP body, query string, message payload): a bad value there is routine and expected, so the boundary **collects** the violations and **returns** them — the controller answers `422`. An **invariant** is a rule that *defines* a domain concept and must always hold; violating it is exceptional, so the domain **throws**. A self-contained format rule (length, pattern, range, syntax) *is* the value object's invariant — "a string without `@` is not an `Email`" — validated in the VO constructor.

`request-dtos.md` ("Layered validation — what belongs where") is the **canonical placement model** across all layers (DTO/zod → value object → entity invariant → use case), including why the overlap between them is deliberate defense in depth. Don't restate it here. The one consequence to keep in mind while writing domain code: **a value object can only see its own value**, so a rule that compares two fields (`closedAt` after `openedAt`) cannot live in a VO — it belongs on the entity/aggregate root; a rule that needs another aggregate or a repository read belongs in the use case.

### Two anti-patterns

- **Duplicating a format rule across files.** If two value objects enforce the same format, factor the shared check into one abstract base (or a shared validator in `domain/`) so the rule is defined **once**:

  ```ts
  // domain/Token.ts — the format rule lives here, and only here
  const TOKEN_REGEX = /^[0-9a-f]{64}$/i;

  export abstract class Token {
      constructor(protected readonly value: string) {
          if (!TOKEN_REGEX.test(value)) throw new Error("Invalid token format");
      }
      getValue() { return this.value; }
  }
  // e.g. AccessToken extends Token; TokenDigest extends Token
  ```

  Never copy a format constant (`const SOME_REGEX = /.../`) into a second file. A repeated literal validation rule is a missing shared value object.

- **Treating an internally-generated value as input validation.** External input that reaches a VO is still validated there even though a schema already checked it (defense in depth — see `request-dtos.md`). But a value the system *produces* (a hash from a hasher, a generated id, a computed token) never arrives from outside, so there is no boundary layer to overlap with and its check is **not** input validation. Do **not** fold it into the client-facing error-collection (the `ValidationError` whose fields become a `422`) — a client can't fix a field it never sent. Either wrap it in a value object whose constructor throws, or, if the producer already guarantees the format, drop the check as a state that can't happen.

## Domain events

Immutable data-only objects that describe a business fact that has happened. Use them to decouple "something happened" from "who reacts."

Example — an `OrderPlacedEvent` at `domain/OrderPlacedEvent.ts`:

```ts
export default class OrderPlacedEvent {
    constructor(readonly orderId: string, readonly marketId: string) {}
}
```

Rules:

- Fields are `readonly`.
- Payload is primitives and value-object values, never live entity references.
- Raise events from use cases (`mediator.notifyAll("orderPlaced", event)`) after the domain state change is persisted, not from inside the entity.

## Domain errors

Plain `Error("<message>")` is acceptable. For larger services, prefer a small hierarchy so controllers can map to HTTP codes cleanly:

```ts
export class DomainError extends Error {}
export class NotFoundError extends DomainError {}
export class ValidationError extends DomainError {}
export class InsufficientFundsError extends DomainError {}
```

Adopt a hierarchy when the service has enough distinct failure modes that mapping every plain `Error` to HTTP 422 becomes imprecise (e.g., you want 404 for not-found and 409 for conflict).

## Domain services

A **domain service** is a stateless function or class that operates on domain objects but doesn't naturally belong to any one of them (e.g., a price calculator that consumes several aggregates). Place it in `domain/` alongside entities, named by its capability (`OrderMatcher`, `FeeCalculator`).

Use sparingly. If logic fits on an entity, put it there first.

## Do / don't

- **Do** throw from constructors when invariants are violated.
- **Do** use static factories for "new" creation (`createOrder`, `createWallet`).
- **Do** keep constructors accepting primitives plus the persisted fields so repositories can reconstitute cleanly.
- **Do** write unit tests against domain classes in isolation — no mocks, no DI.
- **Don't** import anything from `application/` or `infra/` in a domain file.
- **Don't** inject a repository or gateway into a domain object. If an entity needs external data, the use case fetches it and passes it in.
- **Don't** make fields public. Getters + behavior methods.
- **Don't** let `new Date()` appear inside a rule. Accept a timestamp argument; call sites provide it.
