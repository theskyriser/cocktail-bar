---
paths:
  - "**/*.{ts,tsx}"
---

# Frontend Coding Standards

## Null / Undefined Checks

- Prefer a single nil-check helper over hand-rolled comparisons. If the project already depends on one (e.g. Ramda's `isNil`), use it consistently and import the named function directly rather than the namespace object.
- Use `!isNil(value)` instead of `value !== null && value !== undefined`

## API Fetch Typing

- Do not pass explicit generic type parameters to a typed fetch wrapper when the wrapper can infer them. If the project's wrapper derives its data type from a Zod schema argument, pass the schema and let inference do the rest.
- Restating the inferred type as an explicit generic is duplication that silently rots when the schema changes.

## Boolean Coercion

- Use `!!value` for boolean coercion when checking existence of strings, numbers, or objects — not for arrays where empty `[]` is truthy
- For arrays, use `!!value?.length` to cover null/undefined and empty array in one check

## Component Decomposition

- **Extract action groups into their own components.** When a component has buttons, modals, or action handlers that form a cohesive unit, extract them into a dedicated `*Actions` component. The parent should only render `<ComponentActions />`, not inline buttons and modals.
- **One concern per component.** A view component should orchestrate layout and delegate behavior. It should not own modal state, confirmation flows, or action handlers that belong to a self-contained interaction.
- **Keep view components thin.** If a component imports the modal-state hook, `Button`, and a modal component together with domain-specific hooks, that's a signal to extract an actions component.

## Hook Design

- **One mutation per hook.** Do not create "god hooks" that bundle every mutation for a domain (e.g. a `useEntityActions` exposing create, update, archive, and a status check all at once). Extract each distinct action into its own hook (e.g. `useCreateEntity`, `useArchiveEntity`) when it has its own success handler, error handling, or UI concerns.
- **Colocate hooks with their consumers.** If only one component uses a hook, keep them close in the file tree. Don't force all mutations through a single "actions" hook.

## Test Alignment

- **Tests must mirror the component tree.** When you extract `ComponentActions` from `ComponentView`, the parent test should mock `ComponentActions` and only assert it renders. The behavior tests (click handlers, modals, confirmations) belong in the extracted component's own test file.
- **Mock at the boundary you extracted.** After extracting a child component, the parent test should `jest.mock` the child — not the child's internal hooks. This keeps tests focused on the component's actual responsibility.
- **Remove stale mock return values.** When a hook no longer returns a property, update the mock to match the new return shape. Leftover mock properties mask coupling.
- **Remove unused mock variables.** If mocks are no longer asserted, delete them. Dead mocks are noise.
- **Remove unused imports.** If `fireEvent` is no longer used after removing interaction tests, remove the import.

## General Anti-Patterns to Avoid

- **Wrapper functions that add nothing.** `const handleSubmit = () => { submit() }` adds indirection without value. Pass `submit` directly or use it inline.
- **Importing more than needed.** If a component imports a button, a modal, the modal-state hook, and multiple hooks but only orchestrates layout — those imports belong in the extracted child.
- **Spreading unrelated concerns in one file.** Modal state + API mutation + cache invalidation + UI rendering in one component is a code smell. Separate the "what action to perform" (hook) from the "how to present it" (component).
