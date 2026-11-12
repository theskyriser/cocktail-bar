# Application Layer — Use Cases

> **Names in the examples (`PlaceOrder`, `GetWallet`, `OrderRepository`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

A **use case** is the entry point for a single business operation: place an order, deposit funds, generate an invoice, upload a receipt. It orchestrates domain objects and infrastructure to satisfy that operation. It is **not** where business rules live.

In ports-and-adapters vocabulary, **the use case class is the primary (driving) port**. Controllers, CLI handlers, queue consumers — the *primary adapters* — depend on the use case's shape (via `@inject` typed as the class) and call `execute(input)`. Thanks to TypeScript's structural typing, the class itself serves as the port; a separate `interface PlaceOrderUseCase` is optional ceremony, not a requirement. Extract one only when you have a real need (multiple implementations, a test that can't substitute via the Registry). See `references/architecture.md` for the broader primary/secondary split.

## The shape

Every use case is a class with:

- one or more `@inject(...)` properties typed as **interfaces** from `infra/`,
- a single `async execute(input: Input): Promise<Output>` method,
- explicit `Input` and `Output` types co-located in the same file.

Example — a `PlaceOrder` use case at `application/usecase/PlaceOrder.ts`:

```ts
import { inject } from "../../infra/di/Registry";
import OrderRepository from "../../infra/repository/OrderRepository";
import WalletRepository from "../../infra/repository/WalletRepository";
import Mediator from "../../infra/mediator/Mediator";
import Order from "../../domain/Order";

export type Input = {
    accountId: string;
    marketId: string;
    side: string;
    quantity: number;
    price: number;
};

type Output = {
    orderId: string;
};

export default class PlaceOrder {
    @inject("orderRepository")  orderRepository!: OrderRepository;
    @inject("walletRepository") walletRepository!: WalletRepository;
    @inject("mediator")         mediator!: Mediator;

    async execute(input: Input): Promise<Output> {
        const wallet = await this.walletRepository.getWalletById(input.accountId);
        if (!wallet) throw new Error("Account not found");
        const order = Order.createOrder(input.accountId, input.marketId, input.side, input.quantity, input.price);
        const hasBalance = wallet.blockOrder(order);
        if (!hasBalance) throw new Error("Insufficient funds");
        await this.orderRepository.saveOrder(order);
        await this.walletRepository.updateWallet(wallet);
        await this.mediator.notifyAll("orderPlaced", order);
        return { orderId: order.getOrderId() };
    }
}
```

Notes on this example:

- The use case **does not contain business rules**. `wallet.blockOrder(order)` is the rule; the use case checks the boolean and decides whether to surface an error.
- It calls **interfaces only** (`OrderRepository`, `WalletRepository`, `Mediator`), never concrete adapters.
- Persistence happens after the domain operation succeeds. If `wallet.blockOrder` throws, nothing is written.
- The event (`orderPlaced`) is published after the writes succeed, not in the middle.

## Input and Output types

- Always explicit, never inferred from the call site.
- **Defined in the use-case file, owned by the application layer.** `Input`/`Output` are co-located with the `execute` they describe.
- **Declared at the top of the file**, between the imports and the class (after any module-level constants is fine, but above the `export default class`). The reader sees the use case's contract before its implementation — not buried at the bottom. This is the convention across every use case in the project.
- `Input` is exported when controllers or other use cases need to construct it; `Output` typically stays local.
- No `any`. If a field can be a known set of strings, use a string-literal union (`side: "buy" | "sell"`).
- For optional or polymorphic inputs, model them as discriminated unions, not optional bags.

### The application layer never imports wire DTO types (non-negotiable)

`Input` and `Output` are **application-layer** types. They MUST NOT be — or alias, or `import type` — the HTTP wire DTOs (`CreateContractBody`, `CreateContractOutput`, `z.infer<typeof xxxSchema>`, …) that live in `infra/schema/`. A use case importing from `infra/schema/*` is a **dependency-direction violation**: `application` would depend on `infra`.

```ts
// ❌ WRONG — application reaches outward into infra/schema, and snake_case wire keys leak in
import type { CreateContractBody, CreateContractOutput } from "../../infra/schema/contractSchemas";
async execute(input: CreateContractBody): Promise<CreateContractOutput> {
    const id = TemplateId.create(input.template_id); // wire shape (snake_case) inside the core
    return { reference_id: id, expires_at: expiresAt.toISOString() }; // wire shape on the way out
}

// ✅ RIGHT — application owns a transport-agnostic, camelCase shape
async execute(input: Input): Promise<Output> {
    const id = TemplateId.create(input.templateId);
    return { referenceId: id, expiresAt }; // Date, not a formatted wire string
}
export type Input  = { templateId: string; /* … */ };
type Output        = { referenceId: string; expiresAt: Date; /* … */ };
```

Rules that follow from this:

- **camelCase, domain vocabulary.** No `snake_case` keys anywhere in `application/` or `domain/`. The snake/camel mapping is a wire concern and is the controller's job.
- **No transport/serialization decisions.** Return `Date`, not `date.toISOString()`; return a number, not a formatted string. *How* a value is rendered on the wire (ISO-8601 vs `Y-m-d H:i:s`, snake_case keys) belongs to the adapter, not the use case.
- **The controller is the only adapter.** It maps the parsed wire DTO → application `Input` on the way in, and the application `Output` → wire object (then `outputSchema.parse(...)`) on the way out. See `request-dtos.md` → "Controllers adapt wire ⇄ application".
- **Opaque blobs stay opaque.** If the use case only forwards a value (e.g. JSON-stringify-then-encrypt a payload), type it as `Record<string, unknown>` — do not import the wire schema's inferred type just to name its fields.

**Why this exists (a real regression).** When the use case is typed as the wire DTO, three things break: (1) the application can no longer be driven from a non-HTTP entry point (CLI, queue consumer) without dragging HTTP DTOs along; (2) renaming a wire field forces edits inside `execute`; (3) the "swap a transport = one adapter file" guarantee becomes false. This was found and fixed once already (architecture review §6.1). Do not reintroduce it because two shapes happen to coincide today — they are *different types that currently look alike*, and the coincidence is not a license to couple the layers.

```ts
export type Input =
    | { kind: "deposit"; assetId: string; quantity: number }
    | { kind: "withdraw"; assetId: string; quantity: number };
```

## Validation

Rely on TypeScript types at the use-case boundary. For external boundaries (HTTP body, queue payload), validate at the **adapter** before invoking the use case. zod is the recommended tool:

```ts
import { z } from "zod";

const PlaceOrderBody = z.object({
    accountId: z.string().uuid(),
    marketId: z.string().min(1),
    side: z.enum(["buy", "sell"]),
    quantity: z.number().positive(),
    price: z.number().positive(),
});

httpServer.route("post", "/place_order", async (params, body) => {
    const input = PlaceOrderBody.parse(body); // throws on invalid → 422
    return placeOrder.execute(input);
});
```

This keeps `unknown` from leaking past the controller while preserving the typed `Input` contract on the use case. The use case still gets a fully typed argument.

## Error handling

Use cases throw `Error("<semantic message>")` (or a domain-error subclass) on failure. They do **not** know about HTTP status codes. The HTTP adapter maps thrown errors to responses (default `422`). See `infrastructure.md`.

Common patterns:

```ts
const wallet = await this.walletRepository.getWalletById(input.accountId);
if (!wallet) throw new Error("Account not found");

const account = await this.accountGateway.getAccountById(input.accountId);
if (!account) throw new Error("Account not found");
```

If the failure is a precondition (caller could check it), throw with a clear message. If it's an invariant violation in domain code, let the domain throw (the use case doesn't catch).

## When to split into multiple use cases

One use case per business operation. Sibling reads and writes are **not** the same use case:

- `PlaceOrder` writes a new order.
- `GetOrder` reads one by id.
- `ListOrders` reads many.
- `CancelOrder` mutates an existing one.

Each is its own file, its own class, its own `execute`. They can share repositories.

## Read-only use cases (queries)

Example — a `GetWallet` read use case:

```ts
export default class GetWallet {
    @inject("walletRepository") walletRepository!: WalletRepository;

    async execute(accountId: string): Promise<Output> {
        const wallet = await this.walletRepository.getWalletById(accountId);
        return {
            accountId,
            balances: wallet.balances.map(b => ({ assetId: b.assetId, quantity: b.quantity })),
        };
    }
}

type Output = {
    accountId: string;
    balances: { assetId: string; quantity: number }[];
};
```

For read use cases:

- Map the entity to a **DTO** in the use case. Don't leak the entity to the controller — the controller serializes whatever the use case returns.
- For projections that don't fit the entity model (joins across aggregates, dashboards), the use case can call a **query service** (a thin repository method that returns a row shape) and pass it through.

## Orchestrating events

When a use case produces a domain event, raise it through the `Mediator` after the writes succeed:

```ts
await this.orderRepository.saveOrder(order);
await this.walletRepository.updateWallet(wallet);
await this.mediator.notifyAll("orderPlaced", order);
```

The handler (registered in the controller) decides whether to publish to a queue, send an email, etc. Keeping the side-effect dispatch on the mediator (in-process) lets tests subscribe directly without standing up a broker.

## Use-case anti-patterns

- **Importing a wire DTO type from `infra/schema/`.** `import type { CreateContractBody } from "../../infra/schema/…"` in a use case is a dependency-direction violation and leaks snake_case wire keys into the core. Define `Input`/`Output` in the use-case file; let the controller adapt. See "The application layer never imports wire DTO types" above.
- **Business rules in `execute`.** If you find an `if` that encodes a business invariant, push it into a domain method.
- **Direct `new` of an adapter.** All collaborators come from `@inject`.
- **Multiple operations per `execute`.** Use cases are atomic from the caller's view. If you find yourself writing `if (input.action === "x") ... else ...`, you have two use cases.
- **Returning an entity.** Return a DTO. Entities are domain-internal.
- **Catching and swallowing.** Let errors propagate. The HTTP layer maps them to responses.
- **Reading env vars.** That belongs in `api.ts`.
