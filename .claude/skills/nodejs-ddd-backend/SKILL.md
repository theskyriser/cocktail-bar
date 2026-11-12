---
name: nodejs-ddd-backend
description: Enforce Node.js backend standards — DDD, Clean Architecture, Hexagonal, SOLID, GoF patterns, Registry-based DI, and the strict layering used across backends. Use this skill whenever the user discusses, scaffolds, reviews, or modifies a Node.js/TypeScript backend service — including new services, REST controllers, use cases, domain entities, value objects, MySQL repositories, Azure Blob storage, PDFKit generators, Docker setup, or test pyramids. Also trigger mid-conversation any time the topic shifts to backend design decisions, layer boundaries, dependency direction, or pattern choices, even if the user does not explicitly mention DDD. Targets Node.js + TypeScript (strict), MySQL, Azure Blob Storage, PDFKit, and Docker.
---

# Node.js DDD Backend Standards

## Purpose

This skill captures the backend architecture used across the stack and applies it to new Node.js/TypeScript services. It enforces a single consistent mental model so every service looks the same at the seams: where domain logic lives, how I/O is adapted, how dependencies are wired, how tests are split.

Quality operates at **three levels simultaneously** — *code* (idiomatic TypeScript, strict types), *design* (SOLID, GoF patterns per module), and *architecture* (layer boundaries, ports and adapters). All three are enforced together. Skipping one defeats the others: perfect architecture with sloppy code still fails; perfect code with tangled dependencies still fails.

The architectural model is **Clean Architecture** (Uncle Bob) implemented via **ports and adapters** (hexagonal): the application core (`domain/` + `application/usecase/`) is surrounded by interface adapters (`infra/controller/`, `infra/repository/`, `infra/gateway/`) which are wrapped by framework drivers (`infra/http/`, `infra/database/`, `infra/storage/`, `infra/pdf/`, …). Dependencies point strictly inward. See `references/architecture.md` for the full mapping and the primary/secondary (driver/driven) split.

New services follow this stack:

- **Runtime**: Node.js + TypeScript (strict, no `any`, no non-null casts, no `unknown` leaks)
- **Database**: MySQL
- **Transport**: REST over HTTP
- **File storage**: Azure Blob Storage
- **PDF**: PDFKit
- **Packaging**: Docker for dev and deploy

## Example names are illustrative — never copy them verbatim

Every concrete class, method, file, table, and column name in the reference files — `Order`, `Wallet`, `Balance`, `UUID`, `PlaceOrder`, `OrderController`, `OrderRepository`, `AccountGateway`, `Mediator`, `orderPlaced`, the `orders` table, etc. — is **illustrative only**. These names teach the *shape* of the pattern: how files are laid out, what method signatures look like, how persistence maps to entities, how DI properties are typed.

**When you write real code, use names from the current project's domain.** If the feature is about customers and subscriptions, the entity is `Subscription` (not `Order`), the use case is `RenewSubscription` (not `PlaceOrder`), the table is `subscriptions` (not `orders`), the controller is `SubscriptionController`, and the repository is `SubscriptionRepository`.

Do not assume any name from the reference files is part of the real project. Do not scaffold a `domain/Order.ts`, a `PlaceOrder` use case, an `OrderController`, or an `orders` table unless the user's actual request is about orders. Copy the **structure**, not the identifiers.

This rule overrides any appearance of continuity with the example code. If you catch yourself about to create `Order.ts`, ask: *is this project actually modeling orders?* If not, rename before writing the file.

## When to apply

Apply this skill — proactively, without waiting for explicit instructions — whenever the conversation involves:

- Creating a new backend service or feature
- Writing or reviewing a **controller, use case, domain entity, value object, repository, or gateway**
- Writing or reviewing **MySQL** queries or migrations
- Uploading/downloading files via **Azure Blob Storage**
- Generating **PDFs** with PDFKit
- Setting up **Docker** or `docker-compose` for a Node.js service
- Debating layer placement ("should this live in domain or application?")
- Refactoring for SOLID / GoF patterns / DI
- Writing **tests** (unit, integration) for a Node.js service

If the user is clearly asking a backend question but hasn't named any of these terms, still apply this skill — the patterns should feel like the default, not an opt-in.

## How to use this skill

1. **Read this file first.** It contains the non-negotiables.
2. **Consult the matching reference file** for the topic at hand. The references expand each topic with rules, rationale, and code patterns. Do **not** read all references — read the ones relevant to the current task.

## Reference files

| File | Read when… |
|------|-----------|
| `references/architecture.md` | Laying out folders, discussing dependency direction, placing new code |
| `references/domain.md` | Writing entities, value objects, aggregates, domain events, or domain errors |
| `references/use-cases.md` | Writing or reviewing application-layer use cases |
| `references/dependency-injection.md` | Registering or injecting a dependency, discussing DI, Singleton, Proxy, Registry |
| `references/infrastructure.md` | Writing `HttpServer`, controllers, composition root, wiring the app |
| `references/mysql.md` | Designing `DatabaseConnection`, writing repositories, SQL queries, migrations |
| `references/azure-storage.md` | Uploading, downloading, or signing URLs for Azure Blob Storage |
| `references/pdfkit.md` | Generating PDFs |
| `references/docker.md` | Writing a `Dockerfile` or `docker-compose.yml` |
| `references/typescript.md` | Configuring `tsconfig.json`, discussing typing patterns, eliminating `any` |
| `references/testing.md` | Writing unit or integration tests |
| `references/patterns.md` | Identifying or applying GoF patterns (Factory, Strategy, Observer, Proxy, Registry, Mediator, Decorator, Template Method) |
| `templates/CLAUDE.md` | Starting a new project — drop this at the repo root and fill in the placeholders to codify Docker-only execution and the architectural constraints |

## Non-negotiables (short list)

These apply to every service. Expand each via the corresponding reference file.

### 1. Dependency direction is strictly inward

```
infra → application → domain
```

`domain` imports nothing from `application` or `infra`. `application` imports from `domain` and from **interfaces** defined in `infra` (never concrete adapters). Composition happens exclusively in the composition root (`src/api.ts`).

If a new import crosses these lines, stop and reshape the code until it doesn't.

### 2. Folder layout

```
src/
├── domain/                    # entities, value objects, domain events, domain errors
├── application/
│   └── usecase/               # use-case classes: one per file, PascalCase
├── infra/
│   ├── di/                    # Registry + @inject decorator
│   ├── http/                  # HttpServer interface + Express adapter
│   ├── controller/            # route registration, HTTP ↔ use-case glue
│   ├── database/              # DatabaseConnection interface + MySQL adapter
│   ├── repository/            # repository interfaces + MySQL implementations
│   ├── gateway/               # HTTP/REST adapters for external services. Storage, Queue, Mailer have their own folders — see patterns.md.
│   ├── pdf/                   # PDFKit adapter (if applicable)
│   ├── storage/               # Azure Blob adapter (if applicable)
│   └── queue/                 # message-broker adapter (if applicable)
├── api.ts                     # composition root — the only place concrete adapters are instantiated
```

### 3. Domain objects own invariants

- Value objects validate inputs **in the constructor** and throw `Error(...)` with a semantic message (e.g., `throw new Error("Invalid uuid")`).
- Entities encapsulate state with private fields + explicit getters, and expose behavior as methods (`wallet.withdraw(...)`, `order.fill(...)`) that enforce rules.
- Static factories (`Order.createOrder(...)`, `Wallet.createWallet()`) represent *new* creation; the raw constructor is for *reconstitution* from persistence.
- Domain is deterministic: no network, no clock reads hidden inside business methods, no DB calls.
- **A value object's format rule is its invariant — define it once.** If the same format/regex appears in two files, that's a missing **shared VO base**: extract it, never copy the literal. And never fold an **internally-generated** value's format check into the client-facing `422` error aggregate — it's an invariant (throw), not input validation. Full cross-layer placement model: `references/request-dtos.md` ("Layered validation"); the throw-vs-return rationale: `references/domain.md` ("Validation vs. invariants").

### 4. Use cases have one shape

```ts
export type Input  = { /* explicit */ };
export type Output = { /* explicit */ };

export default class PlaceOrder {
    @inject("orderRepository") orderRepository!: OrderRepository;
    @inject("walletRepository") walletRepository!: WalletRepository;

    async execute(input: Input): Promise<Output> {
        // orchestrate domain + repos/gateways; no business rules here
    }
}
```

`Input`/`Output` go at the **top** of the file (after imports, above the class) so the contract reads before the implementation.

Business rules belong in domain objects, not in `execute`. `execute` is glue.

`Input`/`Output` are **application-layer** types: camelCase, domain vocabulary, transport-agnostic (`Date`, not ISO strings). They are defined in the use-case file and **never import or alias the HTTP wire DTOs from `infra/schema/`** (`CreateContractBody`, `z.infer<typeof xxxSchema>`, …) — doing so reverses the dependency arrow and leaks snake_case into the core. The controller adapts the wire shape ⇄ the application shape in both directions. See `references/use-cases.md`.

### 5. Infrastructure is interface-first — every library is a swappable adapter

Every I/O concern is split into a **port** (interface) and one or more **adapters** (concrete implementations). Ports come in two directions:

- **Primary ports (driver / input side)** — the use cases themselves. Primary adapters (controllers, CLI handlers, queue consumers) call **into** the core.
- **Secondary ports (driven / output side)** — `HttpServer`, `DatabaseConnection`, `BlobStorage`, `PdfGenerator`, `Queue` (for publish), every `Repository`, every `Gateway`, `Mailer`. Secondary adapters implement these ports using a specific library. The core calls **out through** them.

**Swap target: one file + one line.** Switching PDFKit → Puppeteer, MySQL → Postgres, Azure Blob → S3, Express → Fastify, SendGrid → SES is a new secondary-adapter file plus one changed line in `src/api.ts`. No use case, controller, test, or domain file should need to change. If a swap touches any other file, the port leaked library-specific types into the interface — fix the port before continuing.

See `references/architecture.md` ("Clean Architecture — how folders map to the rings" and "Ports and adapters — primary vs secondary") for the full model, and `references/pdfkit.md` ("Swapping the driver") for a worked swap.

### 6. DI is centralized

A single `Registry` (Singleton service locator) holds dependencies. Consumers declare them with `@inject("name")` on class properties. Registration happens only in `src/api.ts`. No `new SomeAdapter()` in controllers or use cases.

See `references/dependency-injection.md`.

### 7. SQL is always parameterized

Never interpolate values into SQL strings. MySQL adapter uses `?` placeholders. See `references/mysql.md`.

### 8. TypeScript is strict

- `strict: true` in `tsconfig.json` (all flags).
- No `any`. No `as` casts except where the compiler genuinely cannot know the type (parsing JSON from external boundaries, and even then map into a typed shape immediately).
- No `unknown` leaking past module boundaries — narrow it with a type guard or zod schema at the boundary.
- `experimentalDecorators: true` is required for the `@inject` pattern; that is the one accepted decorator use.

See `references/typescript.md`.

### 9. Tests follow the pyramid

- `test/unit/` — domain classes, in isolation, no DI.
- `test/integration/` — use cases with real DB and a real Registry.
- `test/load/` — optional, for hot paths.

Every behavior change ships with updated tests. See `references/testing.md`.

### 10. Configuration lives at the composition root

Env vars (connection strings, Azure credentials, ports) are read **only** in `src/api.ts` and passed to adapters via constructor arguments. Adapters do not read `process.env` themselves.

Never read `process.env` inside an adapter or use case.

### 11. Docker-only execution — app, database, and migrations

The project runs **entirely in Docker**, for every environment (dev, CI, prod). No exceptions. That means:

- Never run `npm install`, `npm run build`, `npm test`, `node dist/...`, `tsc`, or `mysql` on the host. Run them inside a compose service (`docker compose exec api npm test`, etc.).
- The database is reachable only on the compose network. No host-side DB client touches it.
- **Migrations are a first-class Docker service** (`migrate` in `docker-compose.yml`) — not a script run on the host, not something that happens at application startup. Schema changes happen only through committed migration files applied via `docker compose run --rm migrate`. In production, migrations run as a pre-deploy job that must succeed before the new app image is promoted.
- The same `Dockerfile` drives dev, CI, and prod. Dev differs only in the volume mount and dev dependencies; every other property — Node version, OS libraries, container user — is identical.

The rule eliminates "works on my machine" at the root. Any deviation is a process defect, not a shortcut.

New projects start from the CLAUDE.md template at `templates/CLAUDE.md` in this skill. See `references/docker.md` for the `Dockerfile`, `docker-compose.yml` including the `migrate` service, and the full migrations workflow.

## How to scaffold a new service

1. **Copy `templates/CLAUDE.md`** from this skill into the new repository's root as `CLAUDE.md`. Fill in the `<...>` placeholders (project purpose, any repo-specific details). This locks in the Docker-only rule and command conventions before any code is written.
2. Create the folder tree under `src/` as above.
3. Write `domain/` classes for the core entities and value objects first. Unit tests alongside.
4. Write `application/usecase/` classes with `@inject` on interface-typed fields.
5. Define repository/gateway **interfaces** in `infra/repository/` and `infra/gateway/`. Keep the interface and its first concrete implementation side by side in the same file.
6. Implement MySQL repositories (`references/mysql.md`).
7. Implement Azure Blob and PDFKit adapters (in `infra/storage/` and `infra/pdf/` — not `infra/gateway/`) if the feature needs them.
8. Write controllers that call `httpServer.route(...)` and delegate to use cases.
9. Wire everything in `src/api.ts`.
10. Write integration tests for each use case with a real MySQL connection.
11. Set up `Dockerfile`, `docker-compose.yml` (with the `migrate` service), and `migrations/` (`references/docker.md`). The stack must be runnable end-to-end via `docker compose up -d` and `docker compose run --rm migrate`.

## Review checklist

When reviewing a backend change, verify:

- [ ] Dependency direction is respected (no `infra` imports from `domain`; **no `infra/schema` wire-DTO imports in `application/usecase`**).
- [ ] Use-case `Input`/`Output` are application-owned and camelCase — no snake_case wire keys, no `Date.toISOString()`/formatting in the core; the controller adapts wire ⇄ application.
- [ ] Business rules live in domain objects, not controllers or use-case glue.
- [ ] No format literal (regex/pattern) duplicated across files — a shared rule lives in one value object/base. No internally-generated value validated as a client-facing `422` field error (that's an invariant, not input validation).
- [ ] New adapters implement an explicit interface.
- [ ] SQL uses parameterized queries.
- [ ] Secrets/URLs are read in `api.ts`, not inside adapters.
- [ ] Tests exist for new behavior and cover error paths.
- [ ] No `any` / unsound casts introduced.
- [ ] Composition root is the only place with `new ConcreteAdapter()`.
- [ ] No identifiers copied from the example code (`Order`, `Wallet`, `UUID`, `PlaceOrder`, `OrderRepository`, etc.) unless the real domain actually involves those concepts.
- [ ] No host-side `npm`, `node`, `tsc`, or `mysql` invocations — everything runs through `docker compose`.
- [ ] Schema changes include a migration file in `migrations/` and were applied via `docker compose run --rm migrate`.
