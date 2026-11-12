# Design Patterns Catalog

> **Names in the examples (`Order`, `Wallet`, `UUID`, `PlaceOrder`, `OrderRepository`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the pattern and vocabulary, not the identifiers.

Services in this stack use a focused subset of GoF patterns plus a few classic enterprise patterns. This file catalogs them with a short description, where each one belongs in the layout, and when to apply it.

The point isn't to use every pattern — it's to recognize the shape when it fits and to use the same vocabulary across the codebase.

---

## Creational

### Factory Method (static factories)

**Where:** `domain/UUID.ts`, `domain/Order.ts`, `domain/Wallet.ts`.

```ts
static createOrder(accountId: string, side: string, quantity: number, price: number) {
    return new Order(UUID.create().getValue(), accountId, side, quantity, price, 0, 0, "open", new Date());
}
```

**When:** Whenever an entity has both *create new* and *reconstitute from storage* paths. The constructor handles reconstitution (every persisted field passed in); a `createXxx` static method handles creation (defaults filled in, IDs generated, timestamps set).

**Why this and not a builder:** Most domain entities have a small enough creation surface that a method beats a fluent builder.

### Singleton

**Where:** `infra/di/Registry.ts`.

```ts
static getInstance() {
    if (!Registry.instance) Registry.instance = new Registry();
    return Registry.instance;
}
```

**When:** *Almost never*. The Registry is the legitimate case: one global lookup table for the process. Everything else that *seems* like a Singleton (a connection pool, a cache, a logger) should be **registered** in the Registry instead, so consumers depend on an abstraction rather than a static.

---

## Structural

### Adapter

**Where:** `infra/http/HttpServer.ts` (`ExpressAdapter`), `infra/database/DatabaseConnection.ts` (`MySqlAdapter`), `infra/queue/Queue.ts` (`RabbitMQAdapter`).

```ts
export class ExpressAdapter implements HttpServer {
    route(method, url, callback) {
        this.app[method](url, async (req, res) => {
            try { res.json(await callback(req.params, req.body)); }
            catch (e) { res.status(422).json({ message: e.message }); }
        });
    }
}
```

**When:** Every external library or framework. The interface defines what the application needs; the adapter translates to the library's API. Switching libraries (Express → Fastify) is then a one-file change.

### Proxy

**Where:** `infra/di/Registry.ts`, inside the `inject` decorator.

```ts
target[propertyKey] = new Proxy({}, {
    get(_t, propertyKey: string) {
        return Registry.getInstance().inject(name)[propertyKey];
    },
});
```

**When:** When you need to defer resolution or interpose on access. The DI Proxy defers dependency resolution until the property is *used*, not when the class is *defined*. Other valid uses: lazy loading, method-call logging, cache-through wrappers. Use sparingly — Proxy is powerful but reduces what static analysis can see.

### Decorator (TypeScript decorators)

**Where:** `infra/di/Registry.ts` (`@inject`), `infra/orm/ORM.ts` (`@model`, `@column`).

```ts
export function inject(name: string) {
    return function (target: any, propertyKey: string) {
        // attach Proxy to the property
    };
}
```

**When:** For **declarative** annotations on classes/properties. The standard endorses `@inject` and reserves judgment on others. If you find yourself writing more decorators, consider whether plain composition or a small helper does the job without the decorator runtime.

### Composite (Aggregate root)

**Where:** `domain/Wallet.ts` owns a `Balance[]`.

The aggregate root presents a single interface (`wallet.withdraw`, `wallet.blockOrder`) that may operate on many child objects. External code never reaches into the children directly.

**When:** When several objects belong together, share invariants, and need to be saved/loaded as a unit.

---

## Behavioral

### Observer / Mediator

**Where:** `infra/mediator/Mediator.ts`.

```ts
register(event: string, callback: Function) { this.handlers.push({ event, callback }); }
async notifyAll(event: string, data: any) {
    for (const h of this.handlers) if (h.event === event) await h.callback(data);
}
```

**When:** For in-process pub/sub. A use case raises an event ("orderPlaced") and one or more handlers (a queue publisher, a metrics emitter) react. Keeps the use case ignorant of the side effects and lets tests subscribe directly.

**Mediator vs Observer:** A Mediator is named (`"orderPlaced"`) and centralizes routing. Pure Observer would have the publisher hold a list of subscribers directly. Mediator is the better fit when the publisher shouldn't know who's listening.

### Strategy

**When:** When two or more variants of an operation share an interface and the choice happens at runtime. Examples: a `PricingStrategy` interface with `RegularPricing` / `PromoPricing` implementations; a `FeeCalculator` with regional variants. Register strategies in the Registry by name, inject the right one per use case.

### Template Method

**Where:** `infra/orm/ORM.ts` — `save` defines the steps (build columns → params → query → values → execute) once; subclasses don't need to reimplement them.

**When:** When a sequence of steps is the same but one or two steps vary. Use sparingly — composition (passing a function in) is often clearer than inheritance.

### Command (use cases as commands)

The application layer's **use cases** are essentially Command objects: each one encapsulates a request as a class with a single `execute` method. This makes them trivially queueable, loggable, and replayable.

```ts
export default class PlaceOrder {
    async execute(input: Input): Promise<Output> { /* ... */ }
}
```

**When:** Always — this is the project's standard application-layer shape.

---

## Enterprise patterns (Fowler, PoEAA)

### Repository

**Where:** `infra/repository/OrderRepository.ts`, `infra/repository/WalletRepository.ts`.

```ts
export default interface OrderRepository {
    saveOrder(order: Order): Promise<void>;
    getOrderById(orderId: string): Promise<Order>;
}
export class OrderRepositoryDatabase implements OrderRepository { /* maps rows ↔ entities */ }
```

**When:** Always, for any entity that gets persisted. Domain code never sees SQL; persistence code never sees business rules.

### Gateway

**Where:** `infra/gateway/AccountGateway.ts`, `infra/gateway/BookGateway.ts`.

**When:** For any **external service** the app calls (HTTP API, FTP, SMTP, S3-style storage). The interface lives next to the adapter; tests use a fake.

**Repository vs Gateway:** Repositories own *our data*; gateways talk to *somebody else's system*. Both have the same shape, but the distinction matters: a repository's storage is part of the service; a gateway's backing service can change independently.

**Gateway vs Storage / Queue / Mailer.** "Outside connection" is a superset. A Gateway specifically wraps a **request/response HTTP (or HTTP-adjacent) service**. Blob storage lives in `infra/storage/` because it has streaming and SAS-token concerns. Message brokers live in `infra/queue/` because they have durability and consumer concerns. Mail lives in `infra/mailer/`. When in doubt, look at the library's shape: if it's "call an endpoint, get a response", it's a Gateway. If it's "stream bytes", "publish/consume", or "send a message and forget", it belongs in its own folder under the matching port type.

### Service Locator (the Registry)

**Where:** `infra/di/Registry.ts`.

A service locator is a centralized place to look up services by name. It's been criticized in some communities (constructor injection is "cleaner"), but the Registry pattern here is intentional: combined with the `@inject` Proxy, it gives the ergonomics of constructor injection without the boilerplate of wiring constructors through the call tree.

**When:** This is the project standard for DI; don't introduce a competing container.

### Composition Root

**Where:** `src/api.ts`.

A single place where all concrete dependencies are instantiated and wired together. **Reading env vars and constructing adapters happen only here.**

**When:** Always — every service has exactly one composition root.

### Unit of Work (transactions)

Introduced in `mysql.md` via the `transaction(fn)` method on `DatabaseConnection`. Use it when a single use case writes to multiple tables that must commit atomically.

---

## SOLID, applied to this stack

- **S — Single Responsibility.** One class per file. A use case does one thing. A repository persists one entity. An adapter wraps one library.
- **O — Open/Closed.** Adding a new adapter (Fastify next to Express, S3 next to Azure) does not modify existing code; it adds a new file that implements the same interface.
- **L — Liskov Substitution.** Adapters are substitutable. The `InMemoryOrderRepository` for tests must satisfy every contract that `OrderRepositoryDatabase` does.
- **I — Interface Segregation.** Interfaces stay narrow. `HttpServer` doesn't expose middleware configuration; `BlobStorage` doesn't expose container management. If a consumer needs only two methods, the interface should have only those two.
- **D — Dependency Inversion.** Application depends on **interfaces** in `infra/`, not on concrete adapters. The composition root injects implementations. This is what makes the rest of the stack work.

---

## Patterns *not* to reach for

- **Active Record** — couples persistence to domain. Keep persistence in Repository, domain pure.
- **Anemic domain model** — entities reduced to data bags with all logic in services. The Wallet/Order entities own their behavior; preserve that.
- **Service Layer of God** — a single `OrderService` with a dozen methods doing everything. Use cases are one-class-one-operation.
- **Inheritance hierarchies of entities** — composition (a `Balance[]` on `Wallet`) is almost always clearer than `class SavingsAccount extends Account`.
- **Generic CRUD repositories** (`Repository<T>`) — they tempt you into shapeless interfaces and obscure intent. Each repository is named and exposes the operations its aggregate actually needs.

---

## Vocabulary cheat sheet

| Term | One-line meaning | Lives in |
|------|------------------|----------|
| Entity | Identity + state + behavior | `domain/` |
| Value object | Immutable, identity-less, validated at construction | `domain/` |
| Aggregate | Cluster of entities with one root, one boundary | `domain/` |
| Domain event | A fact that happened, as data | `domain/` |
| Use case | One business operation, `execute(input)` | `application/usecase/` |
| Repository | Persists/loads an entity | `infra/repository/` |
| Gateway | Talks to an external service | `infra/gateway/` |
| Adapter | Implements an interface using a library | `infra/<concern>/` |
| Composition root | The single wiring file | `src/api.ts` |
| Registry | The DI service locator | `infra/di/` |
| Mediator | In-process event bus | `infra/mediator/` |
