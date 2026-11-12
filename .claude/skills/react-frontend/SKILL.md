---
name: react-frontend
description: React/Next.js frontend conventions (TypeScript, Tailwind CSS). Use when working on React/TypeScript/Tailwind code, components, hooks, routes, or styles in a Next.js frontend project.
---

# React Frontend

## Scope

Applies to React/Next.js frontends written in TypeScript with Tailwind CSS. Where the project
pairs with a Laravel API over REST (and, for realtime, Laravel Echo over a Pusher-compatible
broadcaster), see the `laravel-backend` skill for the other half of the contract.

**Read the project's own config before applying anything below.** The rules here describe
conventions and the reasons behind them; the concrete paths, component names and script names
belong to each project. Check `package.json` for the real commands and the shared UI directory for
what already exists.

## Typical stack

TypeScript · Next.js · React Query · React Hook Form · Zod · Tailwind CSS · ESLint + Prettier ·
Jest + React Testing Library · Playwright · Husky. Laravel Echo where there is realtime.

## Commands

Read the actual script names from `package.json` rather than assuming, and use the package manager
the lockfile implies (a `yarn.lock` means yarn, `pnpm-lock.yaml` means pnpm — mixing them corrupts
the lockfile). You generally need four: dev server, tests, typecheck (`tsc --noEmit`), and lint.

**Always run typecheck, lint and tests before considering a task complete.**

## Rules

- **Use the shared UI components.** Before writing a new component, check the project's shared UI
  directory — buttons, inputs, cards, checkboxes, comboboxes, date pickers, multiselects,
  accordions and text areas are usually already there. A second parallel implementation of a
  button is a divergence in behaviour and accessibility, not just in styling.
- **Never hardcode a colour.** Use the semantic tokens defined in the Tailwind theme config, so
  theming and dark mode keep working.
- **Never inline a raw `<svg>`.** Go through the shared `Icon` component — see
  [rules/icons.md](rules/icons.md).
- **Route modal state through the project's modal hook** rather than ad-hoc `useState` per modal,
  so open/close behaviour, focus trapping and unmount cleanup stay consistent.
- **Build forms with the shared `Form` component** and a Zod resolver — never an untyped form.
- **Overriding component variants:** pass `className` and let `tailwind-merge` resolve the
  conflict (a component built as `cn(variants(...), className)` already lets later classes win).
  Never reach for Tailwind's `!important` modifier (`!`) to force a style — it wins the specificity
  fight by making the class unoverridable by anything downstream.
- **Register new routes** in the project's central route-registry constant, if one exists, rather
  than scattering string literals.
- **Never edit generated files.** Anything produced at build time (generated CSS variable files,
  generated icon-name unions, API client types) must be changed at its source — edits to the
  artefact are silently overwritten on the next build.
- Reference jest mocks by full relative path, not a path alias — alias resolution in the test
  runner can differ from the bundler's, and a mock that fails to apply yields a green test of the
  real implementation.
- **Do not write comments unless 100% necessary.** No inline `//` narration, and no JSDoc/docblocks added merely to describe a component, hook, prop, store or test — naming, types and structure carry that. In test files the `it('…')` string IS the explanation; a comment above it is duplication. Reasoning, trade-offs and context belong in the PR description, run log or commit message, not the source. Write one only when the information cannot be expressed in code, getting it wrong would cause a real defect, and it concerns something outside the file (a non-obvious external constraint, load-bearing ordering, an operator/legacy quirk, a units/currency trap). Exempt because they are code, not commentary: `eslint-disable*`, `@ts-expect-error`, `biome-ignore`, and `no-workarounds`' `// WORKAROUND: …` escape valve. (This is a frontend-strict rule — backend PHP docblocks are expected; see this project's root `CLAUDE.md` § *Comments*.)

## Rules

Detailed rule files live under `rules/` and auto-attach via `paths:` frontmatter when editing matching files:

- [rules/app-structure.md](rules/app-structure.md) — the App Router shell *above* the domain modules: routing, layouts, middleware/auth, prefetch, `common/`
- [rules/coding-standards.md](rules/coding-standards.md)
- [rules/feature-flags.md](rules/feature-flags.md)
- [rules/icons.md](rules/icons.md)
- [rules/infinite-scrolling.md](rules/infinite-scrolling.md)
- [rules/layered-architecture.md](rules/layered-architecture.md)
- [rules/rem-size-guidelines.md](rules/rem-size-guidelines.md)
- [rules/websocket.md](rules/websocket.md)
