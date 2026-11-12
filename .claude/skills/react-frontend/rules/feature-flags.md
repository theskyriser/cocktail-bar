---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# Feature Flags

Feature flags let a feature be enabled or disabled per user, per tenant, or per environment
without a deploy.

When the backend is Laravel, flags are typically **defined server-side** (e.g. with
[Laravel Pennant](https://laravel.com/docs/12.x/pennant)) and exposed to the frontend over a
single endpoint. The frontend is a consumer: it never defines the set of flags, it reads them.

## Wiring

- One fetcher module reads the flags endpoint; everything else consumes its result. Do not fetch
  flags ad hoc from a component.
- **Always define a stub/default set** used when the request fails. A flag service outage must
  degrade to a known-safe state — normally every risky flag off — rather than crashing the app or
  leaving flags undefined.
- The response is a flat map of flag key to boolean:

```json
{
  "data": {
    "feature-example-one": false,
    "feature-example-two": true
  }
}
```

## Accessing a flag

- **Declarative** — a `FeatureFlag` wrapper component for conditional rendering, with a
  `fallback` for the disabled branch:

```tsx
<FeatureFlag flag="feature-example-two" fallback={<DisabledState />}>
  <EnabledState />
</FeatureFlag>
```

- **Imperative** — a `useFeatureFlags` hook (which the component itself uses) when the decision
  drives logic rather than markup.

## Rules

- Reference flag keys through a shared constant or union type, not scattered string literals — a
  typo in a flag name reads as "feature off" and fails silently.
- A flag is a temporary scaffold. When a feature is fully rolled out, delete the flag and the
  dead branch; do not leave permanently-true flags behind.
- Do not add a flag for behaviour that is already restricted by a role, permission, or tenant
  scope — that restriction is already the gate, and a second one just adds a thing to forget.
