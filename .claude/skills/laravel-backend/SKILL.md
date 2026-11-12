---
name: laravel-backend
description: Laravel backend conventions and scaffolding patterns. Use when working in a Laravel backend project (PHP, Sail, Spatie Laravel-Data), creating new domains/endpoints/repositories/services, or touching Laravel migrations/seeders/tests. Covers stack, commands, directory structure, DTOs, Controller-UseCase-Repository architecture, Manager-Driver pattern, and test conventions.
---

# Laravel Backend

Conventions, commands, and scaffolding patterns for a domain-oriented Laravel API.

**These are conventions, not a map of a specific codebase.** Concrete paths, class names and
script names belong to each project — read the project's own rules file and look at a sibling
domain before scaffolding anything new.

## Typical stack

- Laravel, PHPUnit, PHPStan (level 8), Laravel Pint
- Spatie Laravel-Data for DTOs, with a three-tier naming convention:
  - DTOs that pass data **into** a Use Case are suffixed `Data`
  - DTOs that return data **out of** a Use Case are suffixed `Resource`
  - Internal value objects passed between components are suffixed `Dto`
- Controller → UseCase → Repository layering
- Manager-Driver pattern for external services
- Often run locally via Laravel Sail (Docker)

## Commands

**Read the project's own setup before running anything** — check `composer.json` scripts, and
whether the project uses Sail. If it does, PHP commands run through Sail (`sail artisan …`,
`sail exec … phpstan`) rather than the host PHP, because the host often has a different PHP
version and no access to the containerised database.

The four you need: boot the app, run Pint (`pint --test` to check without rewriting), run PHPStan,
and run tests (scoped with `--filter`, see below).

## Rules

- **Run Pint and PHPStan before considering a task complete.** Both must be clean.
- **Always create a migration file** for schema changes — never alter schema by hand.
- **DO NOT execute migrations or seeders without explicit permission.** Running one against the
  wrong connection is destructive and not always reversible — confirm which database the
  configured connection points at first.
- **Always write a test file.** Tests use the `#[Test]` attribute.
- **Scope test runs to what you changed** (`artisan test --filter=…`). A full Laravel suite can
  take tens of minutes; the per-change signal comes from the targeted tests plus the static gates.
- **A UseCase never touches Eloquent directly** — all queries and model access go through a
  repository.

## Scaffolding Rules

Each scaffolding rule contains the full template and instructions for a specific component type. Read the relevant rule file based on what needs to be created.

### New Domain

**When to use**: A feature requires a new domain that doesn't exist under `app/Domains/`.

See [rules/new-domain.md](rules/new-domain.md)

### New Endpoint

**When to use**: Adding a new API endpoint (Controller, UseCase, Repository, Route, Test) to an existing domain.

See [rules/new-endpoint.md](rules/new-endpoint.md)

### New Repository

**When to use**: Adding a new repository (Contract + Implementation) to an existing domain for database access.

See [rules/new-repository.md](rules/new-repository.md)

### New Service

**When to use**: Adding a new service — either a Manager-Driver pattern for external APIs, or a simple internal service for cross-domain logic.

See [rules/new-service.md](rules/new-service.md)

## Coding Rules

- **Docblocks are fine; inline narration is not.** `/** … */` on classes, methods, DTO properties and repository contracts is expected here, and often mandatory — PHPStan L8 needs `@param`/`@return` array shapes and generics. Keep writing those. Inline `//` comments: do not write one unless it carries information the code cannot (a non-obvious external system or legacy integration quirk, a load-bearing ordering, a units/currency trap). Do not narrate what the code says or restate a test's own name. See this project's `CLAUDE.md` § *Comments* for the full policy, if one exists.

Additional rule files live under `rules/` and auto-attach via `paths:` frontmatter when editing matching files:

- [rules/coding-standards.md](rules/coding-standards.md)
- [rules/email-guide.md](rules/email-guide.md)
- [rules/feature-flags.md](rules/feature-flags.md)
- [rules/pagination.md](rules/pagination.md)
- [rules/testing.md](rules/testing.md)
