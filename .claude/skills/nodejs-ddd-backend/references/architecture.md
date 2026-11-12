# Architecture — Layering and Dependency Direction

> **Names in the examples (`Order`, `Wallet`, `UUID`, `PlaceOrder`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

## The rule

```
infra  →  application  →  domain
```

Dependencies point **inward only**. Arrows never reverse.

- `domain/` imports **nothing** from `application/` or `infra/`.
- `application/` imports from `domain/` and from **interfaces** declared in `infra/` (not concrete adapters).
- `infra/` imports from everywhere it needs to — it is the outer ring.
- Concrete adapters are instantiated in **exactly one place**: `src/api.ts`.

This is the single rule from which everything else follows. When in doubt on where code belongs, ask: *"Which direction does this make the arrow point?"*

## Folder layout

Use this layout:

```
src/
├── domain/
│   ├── <Entity>.ts                # one class per file
│   ├── <ValueObject>.ts
│   └── <DomainEvent>.ts
├── application/
│   └── usecase/
│       └── <UseCase>.ts           # one use case per file
├── infra/
│   ├── di/
│   │   └── Registry.ts            # Singleton registry + @inject decorator
│   ├── http/
│   │   └── HttpServer.ts          # interface + ExpressAdapter in the same file
│   ├── controller/
│   │   └── <Feature>Controller.ts
│   ├── database/
│   │   └── DatabaseConnection.ts  # interface + MySQL adapter
│   ├── repository/
│   │   └── <Entity>Repository.ts  # interface + DB implementation
│   ├── gateway/
│   │   └── <Service>Gateway.ts    # interface + HTTP implementation
│   ├── storage/
│   │   └── BlobStorage.ts         # interface + Azure adapter (if used)
│   ├── pdf/
│   │   └── PdfGenerator.ts        # interface + PDFKit adapter (if used)
│   └── queue/
│       └── Queue.ts               # interface + broker adapter (if used)
└── api.ts                         # composition root
```

Two conventions worth calling out:

1. **Interface + adapter in one file.** Put `HttpServer` (interface) and `ExpressAdapter` (implementation) together in the same `HttpServer.ts`. Same for `DatabaseConnection.ts`, every `*Repository.ts`, every `*Gateway.ts`. This keeps the contract and its first implementation visible together. Add a second file only when you need a second adapter (e.g., an in-memory test double).
2. **One class per file.** File name matches the class name in PascalCase. No barrel files; explicit imports.

## Clean Architecture — how folders map to the rings

The layout is Uncle Bob's Clean Architecture expressed as files. Four concentric rings, dependencies point inward:

| Ring | Name | Folder | Contents (illustrative) |
|------|------|--------|-------------------------|
| 1 | **Entities** (Enterprise Business Rules) | `src/domain/` | Entities, value objects, aggregates, domain events, domain errors |
| 2 | **Use Cases** (Application Business Rules) | `src/application/usecase/` | One class per business operation, each with `execute(input)` |
| 3 | **Interface Adapters** | `src/infra/controller/`, `src/infra/repository/`, `src/infra/gateway/` | Controllers (translate HTTP → use case), repositories (translate DB → entity), gateways (translate third-party API → domain) |
| 4 | **Frameworks & Drivers** | `src/infra/http/`, `src/infra/database/`, `src/infra/storage/`, `src/infra/pdf/`, `src/infra/queue/`, `src/infra/mailer/` | Concrete library wrappers: Express, mysql2, `@azure/storage-blob`, PDFKit, amqplib, SMTP client |

Rings 3 and 4 both live under `src/infra/` because their dependency direction is the same (both point inward). The sub-folder by concern (`http/`, `database/`, `storage/`, …) is what matters — separating rings 3 and 4 into two top-level folders would add ceremony without clarity.

For REST services, the classic "Presenter" role collapses: the use case returns a DTO, the controller serializes it to JSON. No separate presenter object is needed.

Quality lives at three levels simultaneously, and the skill addresses all three:

- **Code** — idiomatic TypeScript, strict typing, no `any` (`references/typescript.md`).
- **Design** — SOLID, GoF patterns applied per module (`references/patterns.md`).
- **Architecture** — layer boundaries, dependency direction, ports and adapters (this file).

Skipping any level is a defect: perfect architecture with sloppy code still fails; perfect code with tangled dependencies still fails.

## Ports and adapters — primary vs secondary

The interface-plus-adapter convention implements the **ports and adapters** (hexagonal) pattern. Two concepts have to be clear before anything else in this file clicks.

### Ports

A **port** is an interface the application owns — the seam between *core* (domain + use cases) and the *outside world*. Ports come in two directions:

- **Primary port** (a.k.a. **driving port** / **input port**). The interface the application **exposes** so outside actors can call in. In this stack, primary ports are **the use cases themselves** — `PlaceOrder.execute(input)`, `GenerateReceipt.execute(input)`. TypeScript's structural typing makes the class *be* the port; callers depend on its shape. Extracting an explicit `interface PlaceOrderUseCase` is fine when it helps (multiple implementations, harder-to-mock edge cases) but not mandatory.
- **Secondary port** (a.k.a. **driven port** / **output port**). The interface the application **depends on** to reach the outside world. Examples: `OrderRepository`, `BlobStorage`, `PdfGenerator`, `AccountGateway`, `Mailer`, `Queue`. Always declared as an explicit `interface` in `infra/<concern>/`.

### Adapters

An **adapter** is a concrete implementation that sits between a port and a specific technology:

- **Primary adapter** (a.k.a. **driving adapter** / **input adapter**). Converts an external input — HTTP request, CLI command, queue message, scheduled tick — into a call on a primary port. Examples: an HTTP controller, a CLI command handler, a queue consumer. Lives in `infra/controller/`, `infra/consumer/`, `infra/cli/`.
- **Secondary adapter** (a.k.a. **driven adapter** / **output adapter**). Implements a secondary port using a specific library or service. Examples: the MySQL repository implementation, the Azure Blob implementation, the PDFKit implementation, the HTTP gateway implementation. Lives beside its port in `infra/repository/`, `infra/gateway/`, `infra/storage/`, `infra/pdf/`.

### The hexagon — what's on each side

```
  ┌──────────────────┐          ┌────────────────┐          ┌────────────────────┐
  │  Primary actors  │          │                │          │  Secondary actors  │
  │                  │          │   ┌────────┐   │          │                    │
  │  Human user      │ ──HTTP──▶│   │  Use   │   │ ──SQL───▶│  MySQL database    │
  │  Mobile / SPA    │          │   │ cases  │   │          │  Azure Blob        │
  │  Other services  │          │   └────────┘   │          │  SMTP server       │
  │  Queue consumer  │          │    (domain)    │          │  Remote HTTP API   │
  │  Test harness    │          │                │          │  Message broker    │
  └──────────────────┘          └────────────────┘          └────────────────────┘
   ↑ primary adapters                                         ↑ secondary adapters
   Controllers                                                Repositories
   CLI handlers                                               Gateways
   Queue consumers                                            Storage, PDF, Mailer
```

**Control flow** goes from the outside in: primary actors push requests into the core; the core pushes commands out to secondary actors. **Dependency direction** always points inward: both adapter rings depend on the core's ports, never the reverse.

### Where each concern lives

| Concern | Side | Port | Adapter (example) | Folder |
|---------|------|------|-------------------|--------|
| HTTP ingress framework | Primary | `HttpServer` | `ExpressAdapter` | `infra/http/` |
| REST route → use case | Primary | (the use case class) | `OrderController` | `infra/controller/` |
| Queue consumer | Primary | (the use case class) | `OrderPlacedConsumer` | `infra/consumer/` |
| CLI command | Primary | (the use case class) | `MigrateCliCommand` | `infra/cli/` |
| Scheduled job | Primary | (the use case class) | `NightlyReconcileJob` | `infra/scheduler/` |
| Persistence | Secondary | `OrderRepository` | `OrderRepositoryDatabase` | `infra/repository/` |
| Third-party HTTP service | Secondary | `AccountGateway` | `AccountGatewayHttp` | `infra/gateway/` |
| File storage | Secondary | `BlobStorage` | `AzureBlobStorage` | `infra/storage/` |
| PDF generation | Secondary | `PdfGenerator` | `PdfKitGenerator` | `infra/pdf/` |
| Email / notifications | Secondary | `Mailer` | `SendGridMailer` | `infra/mailer/` |
| Queue **publish** | Secondary | `Queue` | `RabbitMQPublisher` | `infra/queue/` |

One concern — the message broker — commonly lives on both sides: **publishing** is secondary (the core calls out), **consuming** is primary (the broker calls into the core). Keep them in separate adapter classes (`RabbitMQPublisher`, `RabbitMQConsumer`) even if they share a connection.

### Why this matters

- **Swapping a library is a one-file code change plus a one-line composition change.** Replacing PDFKit with Puppeteer: write `PuppeteerPdfGenerator implements PdfGenerator`, change `new PdfKitGenerator()` to `new PuppeteerPdfGenerator()` in `src/api.ts`. No use case, controller, test, or domain file is touched. Same mechanical move for MySQL → Postgres, Azure Blob → S3, Express → Fastify, SendGrid → SES.
- **Two adapters can coexist.** A feature-flagged migration, a staging-vs-prod swap, or a per-tenant choice is a conditional `new` in `api.ts`, not a conditional in business code. Register both under different Registry keys if both are needed at runtime.
- **Tests always use the port, never the library.** In-memory fakes (`InMemoryBlobStorage`, `InMemoryPdfGenerator`, `InMemoryOrderRepository`) implement the same interface and get substituted via the Registry. Tests never mock `pdfkit`, `mysql2`, or `@azure/storage-blob` — they mock the port.

### How to know the port is airtight

After a swap, ask: *which files outside `infra/<concern>/` needed to change?* The answer must be **exactly one line in `src/api.ts`**. If anything else changed, the port was leaking. Common leaks:

- The interface exposes a library-specific type (e.g., `PDFKit.PDFDocument` on a return type, or `PoolConnection` from `mysql2`). Replace with a neutral shape (`Readable`, a plain row type).
- A use case imports the concrete adapter instead of the interface (`import { PdfKitGenerator }` instead of `import PdfGenerator`). Fix the import.
- A controller or use case calls a library directly (`import pdfkit`). Move the call into an adapter behind a port.

Fix the leak, then re-do the swap. One-line swaps are a non-negotiable target, not an aspiration.

### A note on the word "driver"

"Driver" is overloaded. Two valid senses appear in this skill:

- **Hexagonal driver** — a *primary* actor / adapter: something that **drives the application** by calling into it. The *driver side* of the hexagon (left in the diagram above).
- **Colloquial driver** — a concrete low-level library ("database driver," "PDF driver"). In hexagonal vocabulary that's a *secondary adapter*.

Both usages appear. Context disambiguates: "PDFKit driver" means the library-side PDF adapter (secondary); "primary driver" or "driver-side adapter" means the HTTP / CLI / queue input side.

See `references/pdfkit.md` for a worked secondary-adapter swap example.

## What lives where

### `domain/`

- Entities (objects with identity that change over time): `Order`, `Wallet`, `Account`.
- Value objects (immutable, identity-less, validated at construction): `UUID`, `Money`, `Email`.
- Domain events (data-only DTOs describing a fact): `OrderPlacedEvent`.
- Domain errors (optional; usually plain `Error("message")` suffices).

**Forbidden in domain:** `import express`, `import mysql2`, `import axios`, `import fs`, any decorator, any `process.env` read.

### `application/`

- Use cases: classes with a single `async execute(input): Promise<Output>`.
- Nothing else. No HTTP, no SQL, no file I/O directly — all of that flows through repository / gateway **interfaces** injected via `@inject`.

**Forbidden in application:** importing a concrete adapter class (e.g., `import { OrderRepositoryDatabase }`). Import the interface only (`import OrderRepository`).

### `infra/`

- Adapters that implement the interfaces application depends on.
- Glue code that translates between transport (HTTP, queue, CLI) and use cases.
- The DI registry.
- `api.ts` (composition root).

**Allowed only in infra:** `express`, `mysql2`, `axios`, `@azure/storage-blob`, `pdfkit`, any env var reads.

## Composition root

`src/api.ts` is the only file that:

- reads env vars,
- instantiates concrete adapters (`new MySqlAdapter(...)`, `new AzureBlobStorage(...)`),
- registers them in the `Registry`,
- starts the HTTP server.

Pattern:

```ts
import "dotenv/config";
import Registry from "./infra/di/Registry";
import { ExpressAdapter } from "./infra/http/HttpServer";
import { MySqlAdapter } from "./infra/database/DatabaseConnection";
import { OrderRepositoryDatabase } from "./infra/repository/OrderRepository";
import PlaceOrder from "./application/usecase/PlaceOrder";
import OrderController from "./infra/controller/OrderController";

async function main() {
    const httpServer = new ExpressAdapter();
    const db = new MySqlAdapter({
        host: process.env.DB_HOST!,
        user: process.env.DB_USER!,
        password: process.env.DB_PASSWORD!,
        database: process.env.DB_NAME!,
    });

    Registry.getInstance().register("httpServer", httpServer);
    Registry.getInstance().register("databaseConnection", db);
    Registry.getInstance().register("orderRepository", new OrderRepositoryDatabase());
    Registry.getInstance().register("placeOrder", new PlaceOrder());

    new OrderController();
    httpServer.listen(Number(process.env.PORT ?? 3000));
}

main();
```

Everything else in the service is a pure consumer of this wiring.

## Placement decision tree

When deciding where a new piece of code belongs, walk down:

1. Does it encode a **business rule or invariant**? → `domain/`.
2. Does it **orchestrate** domain objects and I/O to satisfy a use case? → `application/usecase/`.
3. Does it **talk to the outside world** (DB, HTTP, filesystem, queue, clock, random)? → `infra/`.
4. Does it **wire things together**? → `src/api.ts`.

If the answer is "more than one of these," the code needs to be split until each piece has a single answer.

## Smells

- `import express` anywhere outside `infra/`.
- A use case `new`-ing a repository or gateway.
- A controller calling a repository directly (bypassing a use case).
- Domain classes exposing public mutable fields (use getters + behavior methods).
- Anything in `domain/` reading `process.env`.
- SQL strings built by concatenation instead of parameter binding.
- `any` in a method signature — upgrade to a real type.
