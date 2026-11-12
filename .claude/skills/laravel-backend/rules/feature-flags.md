---
paths:
  - "**/*.php"
---

# Feature Flags

Feature flags enable or disable a feature per user, per tenant, or per environment without a
deploy. On Laravel, they are provided by
[Laravel Pennant](https://laravel.com/docs/12.x/pennant), with the definitions registered at boot
by a dedicated service provider.

A flag is a **temporary scaffold**. Once the feature is stable, remove the flag and the dead
branch — on the backend *and* the frontend. A flag nobody removes becomes permanent branching
that every later reader has to reason about.

Do not add a flag for behaviour that is already restricted by a role, permission, or tenant
scope — that restriction is already the gate.

## Creating a Feature Flag

Flags are [defined as classes](https://laravel.com/docs/12.x/pennant#class-based-features) in the
features domain's definitions directory — one class per flag. The `resolve` method returns the
value (usually a boolean, though Pennant supports richer values).

Scope defaults to the authenticated user; see the
[scope docs](https://laravel.com/docs/12.x/pennant#specifying-the-scope) to resolve against a
different model.

**If the resolution rules are non-trivial, delegate to a domain service** and keep `resolve` a
one-liner — flag definitions are read during incidents, so they must stay scannable.

## How it Works

Pennant persists resolved values in the `features` table. A row is created **lazily**, the first
time a given scope reads the flag, and cached thereafter.

The `scope` column is a morph — the scope model's morph alias plus its ID — so the project's morph
map governs what is stored. Renaming a morph alias therefore orphans existing flag rows.

Two consequences worth knowing before you rely on a flag:

- Because values are **cached after first resolution**, changing the `resolve` logic does not
  retroactively change already-resolved scopes. Purge the stored values when the rule changes.
- Because rows are created lazily, an empty `features` table does not mean the flag is off — it
  means nobody has evaluated it yet.

## Updating a Feature Flag

To activate or deactivate a feature flag for ALL the scopes. You can use the Commands:

- Activate: `php artisan pennant:activate {feature-name-here}`
- Deactivate: `php artisan pennant:deactivate {feature-name-here}`

If, for some reason (not recommended), you need to update for a singular row or scope, please first [check the docs](https://laravel.com/docs/12.x/pennant#updating-values), and then either execute a SQL and/or any additional command.
