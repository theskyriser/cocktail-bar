# Request DTOs — Typing Controller Inputs with Zod

> **Names in the examples (`Order`, `PlaceOrder`, `CreateContract`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers. The "Worked example" section at the end is the only block that pins real file paths in `econtract-api/`, and it does so deliberately as the migration target.

A **Request DTO** is the typed shape of an inbound HTTP request — its `params`, `body`, `query`, and `headers` — derived from a Zod schema via `z.infer`. Every controller in the project must declare and consume DTOs this way. `unknown` is forbidden inside handler bodies.

## Why this rule exists

Express hands `req.params`, `req.body`, `req.query`, and `req.headers` to handlers as user-controlled data with no compile-time shape. The Express 5 docs are explicit:

> "As `req.query`'s shape is based on user-controlled input, all properties and values in this object are untrusted and should be validated before trusting." — https://expressjs.com/en/5x/api.html

Without a DTO discipline, three things rot:

1. **Types lie.** Handlers either accept `unknown` (and read it with `as`/`!` — banned by `no-workarounds/SKILL.md`) or hand-write parallel `interface` declarations that drift from the schema.
2. **Parsing duplicates.** Every handler reinvents `safeParse → on-failure-throw → on-success-narrow`, and small differences accumulate into bugs (one route forgets `safeParse`, the next allows `[].toString()` past a `string` field, …).
3. **The wire contract becomes invisible.** A reader looking at the controller can't tell what's accepted without grepping for the schema.

The rule below collapses all three problems into one symmetric pattern.

## The rule

1. **Every endpoint declares four typed slots — `Params`, `Body`, `Query`, `Headers` — plus an `Output` type.** Empty slots use `emptySchema` / `emptyHeadersSchema`.
2. **All five types are `z.infer<typeof xxxSchema>`.** Hand-written `interface` or `type {…}` for request shapes is forbidden.
3. **Every `httpServer.route(...)` call passes a `schemas` argument: `{ params, body, query, headers }`.** The four DTO types are inferred from the schemas object via `z.infer`. Inline `safeParse` inside the handler body is forbidden — the `validateWithZod` gate (see "Middleware — the `validateWithZod` gate" below) runs it once at the boundary, before the handler is invoked.
4. **Outputs are validated inline** — the controller maps the use case's application `Output` to the wire object and runs `outputSchema.parse(wireObject)` immediately before `return`. The use case returns its own application-layer `Output` (camelCase, `Date`s, no formatting) — **not** the wire DTO and not a raw entity; the controller adapts. See `use-cases.md` → "The application layer never imports wire DTO types".
5. **No `unknown`, `any`, `as`, or `!` survive the parse step.** The handler reads parsed values from the gate; everything is typed.

These five points are the compile-time + runtime contract. Type inference alone does not validate at runtime — schemas are erased after compilation. Parse must always run; the `validateWithZod` gate is the single place it does.

## Layered validation — what belongs where

The DTO is **one layer** of a four-layer model. Each layer answers a different question; the overlap between them is intentional and is **defense in depth**, not duplication to delete.

| Layer | Question it answers | Lives in | What it catches | Failure |
|---|---|---|---|---|
| **DTO / Zod schema** | "Is this a *well-formed* request?" | `src/infra/schema/` | Wrong type, missing required, unknown key, malformed UUID/URL, range violations, snake/camelCase mismatch | `ValidationError` → **422** |
| **Value object** | "Is this primitive a valid instance of a domain concept?" | `src/domain/value-object/` | Same format checks (UUID, hex, URL), enforced regardless of caller — test, migration, repo reconstitute | `InvalidValueObjectError` |
| **Entity invariant** | "Does this *state* respect business rules?" | `src/domain/entity/` factory + methods | Cross-field rules (e.g. `expiresAt > now`), lifecycle transitions (`active → signed` ok; `signed → signed` not), aggregate consistency | Domain error → **409** |
| **Use case** | "Are *cross-aggregate* preconditions met?" | `src/application/usecase/` | Referenced template exists & is `PUBLISHED`, no duplicate active contract for `quoteReference`, idempotency replay | Domain error → **404 / 409** |

Rules of thumb:

- **DTO does shape only.** Never "is the template published?", never "does this contract exist?". Those are use-case concerns.
- **VO/entity validate even though the schema already did.** A future test, migration, or repo path may bypass the controller — the domain layer cannot trust its inputs because some upstream layer might have caught the malformed case. That coupling would invert dependencies.
- **Schema-first means fast `422`** at the boundary with the full violation list, before a DB hit, before any business logic. The user gets a clean wire-shape error, not a `500` from a value-object constructor downstream.
- **Overlap is intentional.** `ReferenceId.from(...)` checks UUID even though `referenceIdPathParamsSchema` did — both must be true. Removing one because "the other already validates" is a category error.

See `use-cases.md` ("validate at the HTTP adapter boundary with zod; pass typed `Input` to `execute`") and `domain.md` ("value objects validate at construction") for the full layer contracts.

## Naming conventions

Aligned with `.claude/skills/zod/SKILL.md`:

- **Schemas** — camelCase, `Schema` suffix:
  - `xxxParamsSchema`
  - `xxxBodySchema`
  - `xxxQuerySchema`
  - `xxxHeadersSchema`
  - `xxxOutputSchema`
- **Types** — PascalCase, no suffix (`z.infer<typeof xxxSchema>`):
  - `XxxParams`, `XxxBody`, `XxxQuery`, `XxxHeaders`, `XxxOutput`
- **Files** — schemas live in `src/infra/schema/<feature>Schemas.ts` (one file per related route group, e.g. `contractSchemas.ts` covers create / status / retract). Cross-endpoint partials sit under `src/infra/schema/shared/`.
- **No `*.dto.ts` filename.** The DTO is the *grouping* of five slot schemas + their inferred types per endpoint, expressed inside the existing schema file. The filename convention does not change.

`.strictObject(...)` is the Zod 4 idiom (preferred over `z.object(...).strict()`, which is deprecated). Use it for `Params`, `Body`, `Query`, and `Output`. **Headers are a special case** — see "Headers" below.

## HttpServer port shape

The `HttpServer` port carries the four slot schemas as a single `schemas` argument; the four DTO types are **inferred** from that object via `z.infer`. See **Middleware — the `validateWithZod` gate** below for the gate contract and how `route(...)` mounts it.

```ts
// src/infra/http/HttpServer.ts
import * as z from "zod";
import type { RouteSchemas } from "../middleware/validateWithZod";

export type HttpMethod = "get" | "post" | "put" | "delete";

export type RouteCallback<Params, Body, Query, Headers> = (
    params: Params,
    body: Body,
    query: Query,
    headers: Headers,
) => unknown;

export default interface HttpServer {
    route<
        P extends z.ZodType,
        B extends z.ZodType,
        Q extends z.ZodType,
        H extends z.ZodType,
    >(
        method: HttpMethod,
        url: string,
        schemas: RouteSchemas<P, B, Q, H>,
        callback: RouteCallback<z.infer<P>, z.infer<B>, z.infer<Q>, z.infer<H>>,
        successStatus?: number,
    ): void;
    listen(port: number): void;
}
```

Call sites pass no explicit generics — the `schemas` argument is the single source of truth for slot types. A `route(...)` call without the `schemas` argument fails review (and fails to compile).

## Middleware — the `validateWithZod` gate

Every backend MUST place a single middleware between Express and every controller handler whose only job is: parse the four request slots against the route's Zod schemas, cache the parsed result on the `Request` object, and forward typed values to the handler. The canonical implementation is `src/infra/middleware/validateWithZod.ts`. Controllers never observe an unparsed request, never call `safeParse`, and never call `app.use` / `app.get` / `app.post` directly. The gate is structural, not advisory — it is the runtime mechanism that makes every rule above auto-enforced.

### Contract

The middleware exposes a single factory function that returns a `ValidationGate`. Mirror this file structure in any new Node.js DDD backend; the exports (`RouteSchemas`, `ParsedRequest`, `ValidationGate`, `validateWithZod`) are the contract.

```ts
// src/infra/middleware/validateWithZod.ts
import type { Request, RequestHandler } from "express";
import type * as z from "zod";
import { toValidationError } from "../error/toValidationError";

export type RouteSchemas<
    Params extends z.ZodType,
    Body extends z.ZodType,
    Query extends z.ZodType,
    Headers extends z.ZodType,
> = {
    readonly params: Params;
    readonly body: Body;
    readonly query: Query;
    readonly headers: Headers;
};

export type ParsedRequest<Params, Body, Query, Headers> = {
    readonly params: Params;
    readonly body: Body;
    readonly query: Query;
    readonly headers: Headers;
};

export type ValidationGate<Params, Body, Query, Headers> = {
    readonly middleware: RequestHandler;
    extract(req: Request): ParsedRequest<Params, Body, Query, Headers>;
};

export function validateWithZod<
    Params extends z.ZodType,
    Body extends z.ZodType,
    Query extends z.ZodType,
    Headers extends z.ZodType,
>(
    schemas: RouteSchemas<Params, Body, Query, Headers>,
): ValidationGate<z.infer<Params>, z.infer<Body>, z.infer<Query>, z.infer<Headers>> {
    const cache = new WeakMap<
        Request,
        ParsedRequest<z.infer<Params>, z.infer<Body>, z.infer<Query>, z.infer<Headers>>
    >();

    const middleware: RequestHandler = (req, _res, next) => {
        const params = schemas.params.safeParse(req.params);
        if (!params.success) {
            next(toValidationError("params", params.error));
            return;
        }
        // Express leaves req.body as undefined for requests without a parseable body
        // (GET / DELETE, or POST without a matching Content-Type). Normalise to {} so
        // the body schema sees the empty-object shape it expects.
        const body = schemas.body.safeParse(req.body ?? {});
        if (!body.success) {
            next(toValidationError("body", body.error));
            return;
        }
        const query = schemas.query.safeParse(req.query);
        if (!query.success) {
            next(toValidationError("query", query.error));
            return;
        }
        const headers = schemas.headers.safeParse(req.headers);
        if (!headers.success) {
            next(toValidationError("headers", headers.error));
            return;
        }

        cache.set(req, {
            params: params.data,
            body: body.data,
            query: query.data,
            headers: headers.data,
        });
        next();
    };

    return {
        middleware,
        extract: (req) => {
            const parsed = cache.get(req);
            if (parsed === undefined) {
                throw new Error(
                    "validateWithZod middleware did not run before extract() — programmer error",
                );
            }
            return parsed;
        },
    };
}
```

Three properties of this contract are load-bearing:

- **The cache is a `WeakMap` keyed by `Request`.** `middleware` parses each slot once and stores the typed result; `extract(req)` returns it. The handler reads parsed values without re-parsing — that is *why* inline `safeParse` is forbidden in handlers and how the "no `unknown` survives the parse step" rule auto-holds. The `WeakMap` lets the entry be GC'd with the request, so there is no per-request leak.
- **`extract(req)` throws `"validateWithZod middleware did not run before extract() — programmer error"` when the cache is empty.** This is a structural assertion, not a runtime fallback. The only way to reach `extract(req)` in production code is through the adapter wiring shown below; if it ever throws, the wiring was bypassed.
- **Failures route through `next(toValidationError(slot, error))`, never through `throw` or `res.status(...)`.** `toValidationError` produces a `ValidationError` that the layered-validation table at the top of this rule maps to **422**. The gate does not write to the response, does not log, and does not classify — it forwards. Empty slots are no exception: an `emptySchema` is `safeParse`d like any other, so `?foo=bar` against an empty `query` slot becomes a **422** by construction.

### How `route(...)` consumes the gate

The Express adapter creates one gate per route inside its `route(...)` implementation and mounts the gate's `middleware` immediately before the handler:

```ts
// src/infra/http/HttpServer.ts (ExpressAdapter excerpt)
route<
    Params extends z.ZodType,
    Body extends z.ZodType,
    Query extends z.ZodType,
    Headers extends z.ZodType,
>(
    method: HttpMethod,
    url: string,
    schemas: RouteSchemas<Params, Body, Query, Headers>,
    callback: RouteCallback<z.infer<Params>, z.infer<Body>, z.infer<Query>, z.infer<Headers>>,
    successStatus = 200,
): void {
    const gate = validateWithZod(schemas);
    // Express 5 auto-forwards async rejections (gate or handler) to the error chain.
    this.app[method](url, gate.middleware, async (req: Request, res: Response) => {
        const { params, body, query, headers } = gate.extract(req);
        const output = await callback(params, body, query, headers);
        if (!output) {
            res.status(successStatus).end();
        } else {
            res.status(successStatus).json(output);
        }
    });
}
```

The structural rule: **the gate is created and mounted inside `route(...)`. Controllers never construct `validateWithZod`, never hold a `ValidationGate` reference, and never call `app.use` / `app.get` / `app.post`.** The only entry point a controller has is `httpServer.route(method, url, schemas, callback, successStatus?)`. That single entry point is what makes the five-point rule at the top of this doc auto-enforced — there is no other path through which a request can reach a handler.

Note the absence of a route-level middleware parameter in the `route(...)` signature: it is `(method, url, schemas, callback, successStatus?)`. The only route-level middleware is `validateWithZod`, and it is *injected by the adapter*, not passed by the caller. Other middleware (auth, rate-limit, logging, error-mapping) is mounted **globally**, with path scoping, in the composition root before any controller is constructed. Do not extend `route(...)` with extra middleware slots; scope by path prefix at the composition root instead.

### Anti-patterns specific to the gate

These extend the main "Anti-patterns" section below and fail review on sight:

- Importing `validateWithZod` from a controller and calling it manually. The gate is the adapter's responsibility; controllers see a `route(...)` API and nothing else.
- Calling `httpServer.use(...)`, `app.use(...)`, `app.get(...)`, or `app.post(...)` from inside a controller. Controllers expose only `httpServer.route(...)`. `use(...)` exists for the composition root.
- Reading `req` (or `res`, or any other Express object) directly inside the handler. The handler signature is `(params, body, query, headers)`. The gate's `extract(req)` is internal to the adapter; `req` is not in scope for controllers — that is the whole reason the port abstracts Express away.
- Wrapping the handler body in `try/catch` to convert a `ValidationError` (or any domain error) to a status code. The gate already routed parse failures via `next(err)`; the application's error-mapper middleware converts errors at the boundary. The handler throws; it never classifies.
- Adding a route-level middleware argument to `route(...)` "for flexibility." The signature is fixed. New cross-cutting concerns are mounted globally (with path scoping) at the composition root.

## Empty-slot canonical form

Most endpoints have at least one empty slot. Repeat it the same way every time, via a shared helper in `src/infra/schema/shared/`:

```ts
// src/infra/schema/shared/emptySchema.ts
import * as z from "zod";

export const emptySchema = z.strictObject({});
export type EmptyRequestSlot = z.infer<typeof emptySchema>;
// EmptyRequestSlot ≡ Record<string, never>
```

Use `emptySchema` for `params` on routes without path variables, `query` on routes that ignore the query string, `headers` on routes that don't read any header beyond defaults, and `body` on `GET` / `DELETE` requests.

A DTO surface for a route with only a body therefore looks like:

```ts
export const createContractParamsSchema  = emptySchema;
export const createContractBodySchema    = z.strictObject({ /* … */ });
export const createContractQuerySchema   = emptySchema;
export const createContractHeadersSchema = z.object({ /* … */ });

export type CreateContractParams  = z.infer<typeof createContractParamsSchema>;
export type CreateContractBody    = z.infer<typeof createContractBodySchema>;
export type CreateContractQuery   = z.infer<typeof createContractQuerySchema>;
export type CreateContractHeaders = z.infer<typeof createContractHeadersSchema>;
```

Empty slots **must still be `safeParse`d**: a malicious `?foo=bar` against an `emptySchema` query is a `422`, not a silent pass.

## Headers — practical guidance

Express normalises every header name to lowercase and every header value to `string | string[] | undefined`. A header schema therefore differs from the other three slot schemas:

- **Use `z.object(...)` (loose), not `z.strictObject(...)`.** Node fills `req.headers` with extras (`host`, `user-agent`, `accept`, `content-length`, …). Rejecting unknown keys would `422` every legitimate request.
- **Schema keys are lowercase strings.** `'idempotency-key'`, `'x-signature'`, `'x-timestamp'`. Hyphenated keys force string-quoting; that is the correct shape for HTTP headers.
- **Values arrive as `string | string[] | undefined`.** Single-value headers that may legally arrive as an array (RFC 7230 §3.2.2) need a `z.preprocess(v => Array.isArray(v) ? v[0] : v, …)` step. The shared `optionalIdempotencyKeyHeaderSchema` in `src/infra/schema/shared/headerSchemas.ts` is the canonical example.
- **Express's `Request<P, ResBody, ReqBody, ReqQuery>` does not have a headers generic.** Our `HttpServer` is custom and adds headers as the fourth generic — keeping the four DTO slots symmetric. This is the deliberate reason we don't expose `express.Request` in controllers.

## Worked example — before/after refactor

The canonical migration target is `econtract-api/src/infra/controller/ContractController.ts`, route `POST /contracts`. The identifiers in this section are real, not illustrative.

### Before — current code

```ts
// src/infra/controller/ContractController.ts (excerpt)
this.httpServer.route(
    "post",
    "/contracts",
    async (_params, body, _query, headers) => {
        const bodyResult = createContractInputSchema.safeParse(body);
        if (!bodyResult.success) {
            throw toValidationError("body", bodyResult.error);
        }

        const headerResult = optionalIdempotencyKeyHeaderSchema.safeParse(
            headers?.["idempotency-key"],
        );
        if (!headerResult.success) {
            throw toValidationError("Idempotency-Key", headerResult.error);
        }

        const result = await this.createContractUseCase.execute(
            bodyResult.data,
            headerResult.data,
        );
        const validated = createContractOutputSchema.parse(result);
        return validated;
    },
    201,
);
```

What is wrong against this rule:

- `_params` and `_query` declare untyped, ignored slots. The rule requires every slot to be a real, parseable DTO.
- The `route(...)` call has no generics. `body` and `headers` are `unknown` inside the closure until `safeParse` narrows them.
- The header schema parses a single key (`headers?.["idempotency-key"]`) instead of the headers object as a whole.
- The single combined `createContractInputSchema` does not separate the four DTO slots — it conflates "body" with the whole input.

### After — target code

```ts
// src/infra/schema/contractSchemas.ts (additions / renames)
import { emptySchema } from "./shared/emptySchema";
import { idempotencyHeaderSchema } from "./shared/idempotencyHeaderSchema";

export const createContractParamsSchema  = emptySchema;
export const createContractBodySchema    = z.strictObject({
    quote_reference:     z.string().min(1),
    quote_payload:       quotePayloadSchema,
    current_charges:     z.number().nonnegative().nullable(),
    client_callback_url: z.url().refine((u) => u.startsWith("https://"), "must be HTTPS"),
    template_id:         z.uuid(),
});
export const createContractQuerySchema   = emptySchema;
export const createContractHeadersSchema = z.object({
    "idempotency-key": idempotencyHeaderSchema.optional(),
});

export type CreateContractParams  = z.infer<typeof createContractParamsSchema>;
export type CreateContractBody    = z.infer<typeof createContractBodySchema>;
export type CreateContractQuery   = z.infer<typeof createContractQuerySchema>;
export type CreateContractHeaders = z.infer<typeof createContractHeadersSchema>;
```

```ts
// src/infra/controller/ContractController.ts (after)
this.httpServer.route(
    "post",
    "/contracts",
    {
        params: createContractParamsSchema,
        body: createContractBodySchema,
        query: createContractQuerySchema,
        headers: createContractHeadersSchema,
    },
    async (params, body, query, headers) => {
        // Adapt wire → application Input (snake_case → camelCase) on the way in.
        const result = await this.createContractUseCase.execute(
            {
                templateId: body.template_id,
                quoteReference: body.quote_reference,
                quotePayload: body.quote_payload,
                currentCharges: body.current_charges,
                clientCallbackUrl: body.client_callback_url,
            },
            headers["idempotency-key"],
        );
        // Adapt application Output → wire (camelCase + Date → snake_case + ISO string) on the way out.
        return createContractOutputSchema.parse({
            reference_id: result.referenceId,
            signing_url: result.signingUrl,
            expires_at: result.expiresAt.toISOString(),
        });
    },
    201,
);
```

Diff highlights:

- The `route(...)` call passes a `schemas` argument; the four DTO types are inferred from it via `z.infer`. `params`, `body`, `query`, `headers` arrive at the closure already parsed and typed.
- All four slots are `safeParse`d once by the `validateWithZod` gate before the handler runs — even the empty ones — so `?foo=bar` cannot slip through the query slot.
- The headers slot is parsed as a *whole object*, not by key. The handler destructures the parsed key it needs.
- `_params` / `_query` are gone; the slot names match the inferred DTO types.
- The combined `createContractInputSchema` is split into the four slot schemas. `createContractBodySchema` is the body-only slice it always was in spirit.
- **The controller adapts both directions.** The wire body is mapped into the use case's own camelCase `Input`; the use case's camelCase `Output` (with `Date`s) is mapped back to the snake_case wire object and `parse`d. The use case never sees `template_id` or returns `expires_at` — those are wire names that live only here. This is the boundary that keeps `infra/schema` types out of `application/` (see `use-cases.md`).

The same shape applies to every other route. The `GET /contract/:referenceId/status` route, for example, uses a non-empty `Params` slot (`{ referenceId: string }`), an empty `Body` (it's a `GET`), an empty `Query`, and an empty `Headers`.

## Anti-patterns

The following fail review on sight:

- `httpServer.route("post", "/x", async (params, body, query, headers) => …)` — missing the `schemas` argument; the four DTO types must be inferred from a schemas object passed before the callback.
- Inline `xxxSchema.safeParse(slot)` inside the handler — the `validateWithZod` gate runs it once at the boundary; duplicating it in the controller is forbidden (see "Middleware — the `validateWithZod` gate → Anti-patterns specific to the gate" above for the full list of gate-related pitfalls).
- `params as ReferenceIdParams` — type assertion in lieu of `safeParse`. `no-workarounds/SKILL.md` forbids this.
- `body!.foo` — non-null assertion to silence the compiler. Same prohibition.
- `interface CreateContractRequest { … }` — hand-written request type. The schema is the source of truth.
- `_params`, `_query`, `_body`, `_headers` — slot names with leading underscore. Every slot has a name and a parsed result.
- Reading `body.foo` *before* the `safeParse` succeeds.
- A controller that `import`s `Request` or `RequestHandler` from `express`. The port abstracts those away on purpose.
- Skipping the output validation step (`outputSchema.parse(result)`) on the way out.
- "We don't need a query schema, this route ignores `?…`." Use `emptySchema` and `safeParse` it. An ignored query is still a wire-contract claim.

## Cross-references

- `infrastructure.md` — controllers register routes in their constructors. This rule extends the route signature with the four generics; the registration pattern is unchanged.
- `typescript.md` — `unknown` is acceptable at a boundary, but it must be narrowed immediately. `safeParse` is the legal narrowing path.
- `use-cases.md` — typed `Input` reaches `execute` after the controller adapts the parsed wire DTO into the use case's own application `Input`. **Do not reuse, alias, or `import type` the wire `Body`/`Output` DTOs as the use-case `Input`/`Output`, even when the shapes coincide** — that couples `application` to `infra/schema` and leaks snake_case into the core. The use case owns camelCase, transport-agnostic types; the controller maps wire ⇄ application in both directions.
- `domain.md` — value objects validate at construction. The DTO does not replace them.
- `.claude/skills/zod/SKILL.md` — `Schema` suffix; `z.infer` for types; `safeParse` at boundaries; `z.strictObject` (Zod 4).
- `.claude/skills/no-workarounds/SKILL.md` — type assertions are not a substitute for runtime validation.
