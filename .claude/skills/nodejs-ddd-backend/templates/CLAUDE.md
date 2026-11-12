# CLAUDE.md

> This is a **template**. Drop it at the new project's repository root (as `CLAUDE.md`), fill in the `<...>` placeholders, and remove sections that do not apply. This file is loaded automatically by Claude Code on every session and sets the non-negotiables for the repo.

---

## About this project

<One or two sentences on what this service does, who uses it, and the business domain.>

**Stack**: Node.js + TypeScript (strict), MySQL, REST over HTTP, Azure Blob Storage, PDFKit, Docker.

## How to read this project

Before making any backend changes, consult the **nodejs-ddd-backend** skill. It defines the architectural model this repo adheres to:

- Clean Architecture layers: `domain/` → `application/usecase/` → `infra/`. Dependencies point strictly inward.
- Ports and adapters (hexagonal): every library lives behind a port. Primary adapters (controllers, CLI handlers, queue consumers) call into use cases; secondary adapters (repositories, gateways, storage, PDF, mailer) implement driven ports the use cases depend on.
- TypeScript strictness: no `any`, no unsound casts, no `unknown` leaks past adapter boundaries.
- DI via a single Registry (Singleton service locator) with the `@inject` decorator.
- Testing pyramid: unit tests on domain classes, integration tests on use cases with real adapters.
- Swap target: replacing a library is **one new adapter file + one changed line in `src/api.ts`**.

Ring mapping, folder layout, and full non-negotiables: see the skill's `SKILL.md` and `references/`.

---

## Docker-only execution

**This project runs entirely in Docker — app, database, migrations. No exceptions.**

That means:

- **Never run `npm install`, `npm run build`, `npm test`, or `node dist/...` on the host.** Use the commands in the table below. The host's Node version is irrelevant; CI and every developer share the same container.
- **Never connect to the database from the host shell.** MySQL is reachable only on the compose network. Connect from inside the `api` or `migrate` container.
- **Never apply migrations by hand.** Migrations run as a Docker job (`docker compose run --rm migrate`). The DB schema only changes through committed migration files.
- **Never mutate `node_modules` from the host.** Adding a dependency is `docker compose exec api npm install <pkg>` followed by committing the updated `package-lock.json`.

The rule exists to eliminate "works on my machine": every developer hits the same image, CI hits the same image, prod hits the same image (minus the dev-only volume mount).

---

## Commands

All commands run through Docker Compose. Keep them wired into `package.json` scripts or a `Makefile` so nobody has to remember the raw form.

| Task | Command |
|------|---------|
| Boot full stack (api + mysql + azurite) | `docker compose up -d` |
| Tail application logs | `docker compose logs -f api` |
| Open a shell in the app container | `docker compose exec api sh` |
| Install a new npm dependency | `docker compose exec api npm install <pkg>` |
| Run full test suite | `docker compose exec api npm test` |
| Run unit tests only | `docker compose exec api npm run test:unit` |
| Run integration tests (real MySQL) | `docker compose exec api npm run test:integration` |
| Type-check | `docker compose exec api npm run typecheck` |
| Lint | `docker compose exec api npm run lint` |
| Apply pending migrations | `docker compose run --rm migrate` |
| Scaffold a new migration | `docker compose run --rm migrate npm run migrate:make <name>` |
| Roll back the last batch (dev only) | `docker compose run --rm migrate npm run migrate:rollback` |
| Migration status | `docker compose run --rm migrate npm run migrate:status` |
| Rebuild images after `Dockerfile` changes | `docker compose build` |
| Stop containers (keep volumes) | `docker compose down` |
| Wipe everything including data | `docker compose down -v` |

Code changes in `src/` are picked up automatically via the volume mount — no restart needed. Image-level changes (new dependency, `Dockerfile` edit) require `docker compose build` (or `docker compose up -d --build`).

---

## Migrations

Migrations are a **first-class Docker service** (`migrate` in `docker-compose.yml`), not a script you run on the host.

- Runner: **Knex (`knex@^3`)** — migrations only, not a query builder. See `references/mysql.md` for `knexfile.ts` and authoring rules.
- Location: `migrations/` at the repo root — never inside `src/`.
- Naming: `YYYYMMDDHHMMSS_<description>.ts`, timestamped by `knex migrate:make` so order is unambiguous.
- **Forward-only in production.** Down migrations exist for local dev but are not relied on for rollback; fix-forward is the production strategy.
- The `migrate` service shares the `api` image (same `Dockerfile`) but overrides the command to run the migration runner.
- It `depends_on: mysql` with `condition: service_healthy` so it only runs when the DB is reachable.

**In production**, migrations run as a **pre-deploy job** (an init container in Kubernetes, a one-off task in Azure Container Apps / ACI). The new `api` image is only promoted after the migration job exits 0. Never tie migrations to application startup — that couples deploy ordering to process restart and makes zero-downtime rollouts harder.

Schema changes a PR **must** include:

- A new migration file in `migrations/`.
- Updated repository code that reads/writes the new shape.
- Updated integration tests.

---

## Architecture constraints (non-negotiable in this repo)

Inherited from the skill. See `SKILL.md` for rationale; the rules below are the enforced contract.

- Dependency direction is strictly inward: `infra → application → domain`. No exceptions.
- Business rules live in domain entities and value objects — never in controllers, use-case glue, or repositories.
- Every library goes behind a port (interface). Use cases and controllers depend on interfaces; only `src/api.ts` touches concrete adapter classes.
- No `any`, no unsound `as` casts, no `unknown` leaking past adapter boundaries.
- SQL is always parameterized (`?` placeholders in MySQL). Never string-interpolate values into SQL.
- Env vars are read only in `src/api.ts`; adapters receive config via constructor arguments.
- Composition root (`src/api.ts`) is the only file with `new ConcreteAdapter()` calls.
- Domain files do not `import express`, `import mysql2`, `import axios`, `import fs`, or anything else from infrastructure.

---

## Testing expectations

- Every behaviour change ships with an updated or new test.
- Unit tests for every domain invariant (positive and rejection cases).
- Integration tests for each use case against the real MySQL container.
- Tests mock **ports**, never libraries. No `jest.mock("mysql2")`, no `jest.mock("@azure/storage-blob")`. Register an in-memory fake in the Registry instead.

---

## When stuck, ask the skill

- "Where does this code belong?" → `references/architecture.md` (placement decision tree).
- "How do I write a use case?" → `references/use-cases.md`.
- "How do I inject something?" → `references/dependency-injection.md`.
- "How do I swap the PDF library?" → `references/pdfkit.md` ("Swapping the driver").
- "How do I query / write a repository?" → `references/mysql.md`.
- "How do I upload to Azure?" → `references/azure-storage.md`.
- "Docker / migrations?" → `references/docker.md`.
- "What patterns apply here?" → `references/patterns.md`.

If a question is project-specific (not covered by the skill), extend *this* file rather than the skill.
