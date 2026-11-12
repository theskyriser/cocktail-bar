---
paths:
  - "**/src/app/**"
  - "**/src/middleware.ts"
  - "**/src/common/**"
---

# App Structure (above the domain modules)

How a Next.js **App Router** frontend is assembled *above* the `context.<domain>/`
folders: routing, layouts, auth, data loading and the shared `common/` layer. The
rules *inside* a domain module are in [layered-architecture.md](layered-architecture.md);
component and hook conventions are in [coding-standards.md](coding-standards.md).

> **This is a shape, not a spec.** It describes an arrangement that works, and —
> more importantly — *why each part is where it is*. Read the project's own
> `app/`, `middleware.ts` and `common/constants*.ts` before applying anything
> here; where they differ, they win. When you adopt this document into a repo,
> pin it to a commit (`written against origin/main @ <sha> (<date>)`) so a later
> reader can tell how stale it is.

**Placeholders used below:** `<web-app>` the frontend repo · `<api>` the backend
API · `context.<domain>` a bounded context · `(shell)` the route group carrying
the heavy persistent chrome · `(standalone)` a section that needs its own frame ·
`[tenant_id]` whatever the per-account route param is (partner, org, workspace,
account) · `TENANT_ROUTES` / `INTERNAL_ROUTES` the role-family allow-lists.

---

## 1. Stack at a glance

Record the actual choices per repo — the point of the table is that the answers
are written down somewhere, not that they match these defaults.

| Concern | Typical choice in this pattern |
|---|---|
| Framework | Next.js App Router **only** (no `pages/`), React 19, TypeScript strict |
| Dev server | `next dev --turbopack` on :3000 |
| Build | `output: 'standalone'`, container image, CI pipeline config in-repo |
| Server state | TanStack Query v5, optionally persisted to browser storage via `PersistQueryClientProvider` |
| Client state | Small Zustand stores (`src/common/store`, plus per-domain `store/`) |
| Validation | A zod schema on **every** fetcher (`fetchApi({ schema: { data } })`) |
| UI | Tailwind + a headless primitive library (Radix et al.), local fonts |
| Observability | An error reporter wired for client, server **and** edge runtimes, tunnelled through a first-party path so ad blockers do not eat it |
| Tests | Jest `*.test.tsx` beside the code; E2E suite in `e2e/` — **state plainly whether it runs in CI** |

Path aliases (`tsconfig.json`): `@/*` to `src/*`, `@@/*` to the repo root. Keep
the set small; every alias is another thing the bundler and the test runner must
agree on (see the mock-path rule in [layered-architecture.md](layered-architecture.md) §9).

---

## 2. Top-level layout of `src/`

```
src/
├── middleware.ts          # Edge middleware: auth, role routing, CSP nonce
├── instrumentation.ts     # Server/edge observability init, runtime polyfills
├── app/                   # App Router: routes, layouts, boundaries ONLY (thin)
├── common/                # Cross-domain shell: components, hooks, data, utils, constants
└── context.<domain>/      # Bounded contexts (one folder per context)
```

Repo-root files that belong to the shell rather than to any domain:
`next.config.js`, the client-side observability init, the error-reporter configs,
`tailwind.config.ts`, any CSS-variable build step, a bundle-size budget file,
dead-code config (e.g. `knip.jsonc`), and `patches/` if the project patches
dependencies.

### The split between `app/` and domains

**`app/` is thin.** A route's `page.tsx` imports a page component from the owning
domain and renders it; its `layout.tsx` sets up chrome and server prefetches.
Nothing else:

```tsx
// app/(dashboard)/dashboard/page.tsx
export default function Dashboard() { return <DashboardPage /> }   // from context.dashboard
```

This keeps routing (a framework concern) separate from the domain modules, so a
domain can be tested, moved or renamed without touching the route tree.

**The sanctioned exception** is a section large enough to own its whole stack. It
may live entirely under its route folder with colocated private layers:

```
app/(standalone)/…/<section>/
  _components/ _data/ _domain/ _hooks/ _store/ _utils/ _constants/
```

`_`-prefixed folders are private to Next, so they are not routes. Note the cost
before choosing this: the shared layer ends up importing *into* `app/` (for
example `common/constants.prefetch.provider.ts` registering that section's
fetchers), which is a dependency pointing the wrong way. Take it deliberately,
for one or two sections at most, and write down that you did.

---

## 3. Route map

Route groups `( … )` organise layouts without changing the URL. A representative
tree:

```
app/
├── layout.tsx                    # Root shell (providers — see §5)
├── error.tsx                     # → shared page error boundary fallback
├── global-error.tsx              # Last-resort boundary (own <html>), logs via the shared logger
├── not-found.tsx                 # 404 → link back to /login
├── _styles/                      # globals.css, generated CSS vars, Tailwind theme extension
│
├── login/                        # Auth layout (owned by context.user)
│   ├── page.tsx                  #   /login
│   ├── forgotten-password/ · reset-password/
│   ├── <sso>-callback/           #   identity-provider return
│   └── invitation/… · welcome/
├── register/ · verify-email/ · privacy-policy/ · maintenance/
│
├── (shell)/                      # The heavy, persistent section
│   ├── loading.tsx               #   COLD-load skeleton (see §6)
│   └── tenant/
│       ├── layout.tsx            #   section-wide toasts + full-height frame
│       └── [tenant_id]/
│           ├── layout.tsx        #   THE SHELL: nav, persistent panels, heavy widgets (§6)
│           ├── loading.tsx       #   renders null, so in-shell navigation keeps the shell
│           ├── page.tsx          #   search-param-driven panels
│           └── <feature>/ …      #   routed sub-pages
│
├── (standalone)/                 # SAME URL prefix, SEPARATE layout tree (no shell)
│   └── tenant/[tenant_id]/<section>/ …
│
├── (dashboard)/dashboard/        # Its own nav bar
│
├── internal/                     # Staff-only area, its own nav
│   └── … (+ nested sections)
│
└── api/
    ├── health/route.ts           # GET/HEAD → {status:'healthy'}, unauthenticated
    └── internal/health/route.ts  # Bearer-token gated; 404 when the token env var is unset
```

Points worth knowing — each of these has bitten someone:

- **There may be no `/` page.** If the middleware matches `/`, signed-out users
  go to `/login` and signed-in users fail the role check and are sent to their
  role home page. Nothing ever renders at `/`. Do not add a page there expecting
  it to show.
- **A shared URL prefix is not a shared layout.** `(standalone)` can sit under
  the same `/tenant/[tenant_id]/…` path while living in a different route group,
  so the shell layout does **not** wrap it. Moving between the two swaps the
  entire layout — which is the point when the section needs the full viewport,
  and a surprise when you did not intend it.
- **Much of a shell section's UI may not be routed at all.** Panels opened by
  search params (`?customer_panel=…`, `?detail_panel=…`) are read by a panel
  component, not by a path segment. Deep links to them are query strings, so
  they do not appear in the route tree and do not get their own `loading.tsx`.
- **Server API routes stay minimal.** Health checks and nothing else. Every
  other call goes from the browser or a server component straight to `<api>` —
  there is no BFF layer. Adding one is an architectural decision, not a
  convenience.

---

## 4. Middleware: auth, role routing, CSP

`src/middleware.ts` should be a one-line re-export of the auth middleware owned
by `context.user` — the logic belongs to that context, the file placement is a
framework requirement. The `matcher` covers `/`, the public pages and every
protected prefix. On each matched request:

1. **Mint a CSP nonce.** Set it on the request (`x-nonce`) and set
   `Content-Security-Policy` (plus report-only and reporting endpoints where
   configured) and `Referrer-Policy: no-referrer` on the response. The root
   layout reads the nonce back out of the request headers.
2. **Handle force logout** — a `?force_logout` on the login route clears every
   auth cookie.
3. **Decide authentication** — require *both* a verified claims cookie (signed,
   verified in the middleware) *and* a session cookie. Either one alone is not a
   session.
4. **Unauthenticated + protected route** → redirect to `/login?return_url=…`,
   clearing an orphaned session cookie on the way.
5. **Authenticated** users are redirected to their role home page when any of:
   the route is public-only (e.g. `/login` while signed in); the role's route
   allow-list does not match; a finer permission rule fails.

### Route allow-lists (`common/constants.ts`)

Access is an **explicit allow-list per role family**. A route that is not in a
list cannot be reached — so **a new page must be added to the right list or the
middleware bounces it.** This is the single most common cause of "my new page
redirects to the dashboard".

| List | Who | Contents |
|---|---|---|
| `UNPROTECTED_ROUTES` | anyone, signed-out only | login and its sub-flows, privacy policy, maintenance, register, email verification |
| `TENANT_ROUTES` | tenant-facing roles | `/tenant/:id` and its sub-pages, standalone-section routes, post-login landing, the public pages, dashboard |
| `INTERNAL_ROUTES` | staff roles | every `/internal/*` page **plus all of `TENANT_ROUTES`** — staff can enter any tenant |

Matching is **by segment**: `:param` matches any non-empty segment and the
segment *count* must be equal. So `/tenant/:id/flags/:id` must be listed
separately from `/tenant/:id/flags` — a parent entry does not cover its children.

Each role family also needs a home page (`getRoleBasedRoutes`): tenant roles to
`/tenant/{defaultTenantId}`, staff to `/internal`.

### Finer rules (`context.user/utils/checkRouteAccessPermission.ts`)

A route allow-list is coarse. Per-permission rules go in an **ordered list**
matched by path substring *or by search-param substring*, resolved with
`find()` — so **order matters**, and a broad entry placed first will shadow a
narrower one below it.

| Match | Requires |
|---|---|
| search param `"tab":"REPORT"` | a `VIEW_REPORTS` permission |
| `/flags` | a specific manager role |
| `/internal/<staff-only-section>` | any internal role |
| `/<shared-section>` | tenant-owner **or** internal role |

### Client-side counterpart

Middleware covers navigation only. API-level auth failures are handled once, in
the QueryClient (`common/util/getQueryClient.ts`), so no component repeats it:

- **401** → store the return URL, drop cookies, hard-navigate to the forced
  logout route.
- **403 with a tenant-access code** → eject to the user's home page, guarding
  against a redirect loop when they are already on it.
- **Any other 403** → one de-duplicated toast; the user stays put.
- Queries or mutations that *expect* a refusal (speculative access probes) opt
  out through `meta` flags rather than by catching errors locally.

---

## 5. Root shell (`app/layout.tsx`)

Provider order, outside → in. The order is load-bearing: each layer depends on
the one above it.

```
<html> <body fonts>
  NonceProvider                 # CSP nonce for headless-primitive / devtools styles
    SearchParamsProvider        # app-wide search-param store (wraps useSearchParams)
      ClientPageReporter        # sets the client-page header value for API calls
      ClientViewportReporter    # same for viewport
      QueryClientProvider       # (Persist)QueryClientProvider + cross-tab sync + tab guards
        TooltipProvider
          HydrationBoundary     # root prefetch (current user, streamed)
            NavigationEvents    # route-change events + error-reporter user context
            ToastContainer
            <main><Suspense>{children}</Suspense></main>
<Script nonce>
```

Consequences to understand before changing any of it:

- **Reading request headers for the nonce makes every route dynamic.** That is
  precisely what lets `useSearchParams()` return real values during SSR. Turning
  on PPR, or removing the header read, pushes the app back to client rendering —
  a performance "win" that silently breaks server-rendered, param-driven UI.
- **Prefetch the current user once, at the root**, streamed, when a session
  exists. Every layout below then joins that in-flight request instead of
  starting its own.
- The query-client provider is also where cross-cutting cache behaviour mounts:
  purge the persisted cache when a sibling tab logs out; invalidate or patch
  queries from realtime events; detect duplicated tabs.
- **Bust the persisted cache on every deploy** with a build-id env var set from
  the framework's own build id. A persisted cache that outlives a schema change
  is a zod throw on the user's next visit.

---

## 6. Section layouts

| Layout | What it adds | Server prefetch |
|---|---|---|
| `(shell)/tenant/[tenant_id]/layout.tsx` | The full shell: fullscreen-mode wrapper, navigation progress, the heavy persistent widget (map/canvas/board), the primary list panel, nav bar, menu, help launcher, unsaved-changes guard | **Awaited**: sort/display settings, terms of use, feature flags, first page of the primary list |
| `(shell)/tenant/[tenant_id]/page.tsx` | The param-driven panels + any required modals | Awaited inside its own Suspense: current user, reference data, filters |
| `(standalone)/…/layout.tsx` | Card frame, section header, validation indicator, overlay loading, unsaved-changes guard | current user |
| `internal/layout.tsx` | Backdrop + staff nav | feature flags (streamed) |
| `(dashboard)/dashboard/layout.tsx` | Lazily-loaded dashboard nav | — |
| `login/layout.tsx` | Auth layout | — |

**Split the shell deliberately around the Suspense boundary.** Put the heavy
persistent widget **above** it so it paints on the first flush; put the panels
that hydrate against awaited data **inside** it. Getting this backwards means
the whole shell waits on the slowest prefetch.

### Loading boundaries

- **The cold-load skeleton goes in the route group** (`(shell)/loading.tsx`),
  not in the segment: a `loading.tsx` never wraps the layout in its *own*
  segment, so one placed beside the shell layout will not cover the shell
  mounting.
- **A `loading.tsx` that renders `null`** in the shell segment makes in-shell
  navigation suspend *inside* the shell rather than replacing it. The trade-off
  is explicit: a page awaiting data blanks only the content area, and a slow
  page shows nothing there until it resolves.

---

## 7. Data layer (shared, used by every domain)

```
domain fetcher (context.<domain>/data/getFoo.ts)
   └─ fetchApi(path, { method, schema: { data: zodSchema }, …cache opts, …prefetch headers })
        └─ <api> REST (cookie session + XSRF, client-page / viewport headers)

registered in
   common/constants.data.provider.ts       # reader / writer / pagination maps
   common/constants.prefetch.provider.ts   # the subset callable from server components

consumed by
   client:  useData({ provider: 'getFoo' }, args)      # useQuery wrapper; key = createQueryKey(provider, …args)
            useDataMutation / usePagedData / useInfiniteCursorData / useDataFromCache
   server:  prefetchQuery({ provider, queryClient }, args)        # awaited
            streamPrefetchQuery(…)                                # in flight, streamed to the client
            → <HydrationBoundary state={dehydrateRequest(queryClient)}>
```

- **The provider name *is* the query key.** Server prefetch and client `useData`
  derive the same key from it, so hydration works with no extra wiring — and a
  hand-rolled key anywhere breaks that silently.
- `getQueryClient()` returns **one client per server request** (via
  `React.cache`), shared by every layout and page in that render, and a
  singleton in the browser.
- **Server-side queries never retry**, so a dead API cannot stall the document.
  Errored queries are not dehydrated; the browser refetches them.
- Use **`prefetchQuery` (awaited)** when the server render reads the data. Use
  **`streamPrefetchQuery`** only when the server render of that subtree is a
  loading state anyway (an access gate, a below-the-fold panel).
- Cache tiers and ETag revalidation live in one constants file
  (`common/constants.cache.ts`); queries kept fresh by realtime events are
  listed in another (`common/constants.broadcastQueries.ts`). Both are
  allow-lists — a query absent from them simply does not get that behaviour.
- **A response-shape change updates the zod schema in the same body of work.** A
  mismatch is a runtime throw in front of a user, not a lint warning.

---

## 8. `src/common/` — the shared layer

| Folder / file | Holds |
|---|---|
| `components/` | Shell and shared UI: navigation (panels, nav bars, menus), the heavy persistent widget, tables, `ui/` primitives, modals, the providers from §5, feature-flag and channel wrappers, unsaved-changes machinery, error fallbacks |
| `hooks/` | The `useData/` family, search-param access, feature flags, tenant context (`useGetTenantIdParams`, current-channel/type helpers), navigation and unsaved-changes guards, hover prefetch |
| `actions/` | `server-only` prefetch helpers (plain + cursor-paginated) |
| `data/` | Cross-domain fetchers that belong to no single context: feature flags, documents, notes, legal copy |
| `store/` | Zustand stores for shell state: panel collapse, unsaved changes, floating-UI obstacles |
| `realtime/` | Generic realtime dispatchers; the socket itself belongs to its own context |
| `util/` | `api.fetch*.ts` — **the single fetch choke point** — plus `getQueryClient`, `dehydrateRequest`, auth helpers, logging, toasts, error-reporter options, persisted-query helpers |
| `_domain/` | App-wide enums and schemas: channel, forbidden codes, return-URL and safe-URL schemas |
| `types/` | Shared types, including the user/role/permission unions and role-family groupings |
| `constants*.ts` | `constants.ts` (routes, cookie keys, HTTP codes) plus `.env`, `.env.server`, `.cache`, `.data.provider`, `.prefetch.provider`, `.broadcastQueries` |
| `plugins/` · `fonts/` · `cssvar.ts` | Tailwind plugins, self-hosted fonts, the CSS-variable map |

`common/` is a *shell*, not a dumping ground: something belongs here only when
two or more contexts use it, or the root layout does. Anything used by exactly
one context belongs in that context.

---

## 9. Domain folders (orientation only)

Each `src/context.<name>/` is a bounded context with the same internal shape —
`_domain/`, `components/`, `data/`, `hooks/`, `store/`, `util/`, `constants/`,
with tests and mocks beside the code. The rules for working inside one are in
[layered-architecture.md](layered-architecture.md).

Two kinds of context are imported by the **shell itself**, not just by pages, and
are worth naming explicitly in your copy of this document: the one that owns
**auth** (middleware, cookies, CSP, login flows) and the one that owns the
**realtime socket**.

---

## 10. Adding a page: checklist

1. Create `app/<group>/<path>/page.tsx` and keep it thin — render a component
   from the owning `context.*`.
2. **Add the path to the right allow-list** in `common/constants.ts`
   (`TENANT_ROUTES` / `INTERNAL_ROUTES` / `UNPROTECTED_ROUTES`). If it needs a
   role or permission, add an ordered entry to the finer-rules list.
3. If it introduces a new top-level prefix, add it to the `matcher` in
   `src/middleware.ts` — otherwise auth and CSP never run for it.
4. To server-render data, register the fetcher in
   `constants.prefetch.provider.ts` and `prefetchQuery` it in the nearest layout
   or page, inside a `HydrationBoundary`.
5. Decide whether the page belongs **inside the shell** (the `(shell)` group) or
   needs its own frame (a `(standalone)` group), and say which in the PR.
6. Run typecheck, lint and tests — and check the page both cold (full reload) and
   warm (in-app navigation): the two paths hit different loading boundaries.
