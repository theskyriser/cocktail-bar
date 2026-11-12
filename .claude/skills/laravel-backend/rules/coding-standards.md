---
paths:
  - "**/*.php"
---

# Backend Standards

## Type Safety

- PHP 8.3, PHPStan level 8
- All public methods must declare return types and type-hint all parameters
- Use `readonly` for immutable classes — especially Use Cases. Do not use `readonly` on Spatie Data/Resource/Dto classes (Spatie needs internal property access).
- **NEVER use `@phpstan-ignore-next-line`** — fix the underlying type issue. Common resolutions:
  - Broad return types: narrow with `/** @var Collection<int, Model> $var */`
  - Union type matching: use `instanceof` chains, not `match ($x::class)`
  - A method missing from a third-party builder/class stub: find the API the package actually
    exposes rather than suppressing the error — the suppression usually means the call is wrong,
    not that the stub is

## Array Access

- **Use `data_get()` for accessing nested array/object values** — not manual chaining with `??`
- `data_get($array, 'key')` returns `null` by default if the key doesn't exist
- Supports dot notation for nested access: `data_get($data, 'nested.path.value')`

```php
// ✅ Correct
$reference = data_get($model->external_keys, 'referenceNumber');
$nested = data_get($config, 'services.example.timeout');

// ❌ Avoid
$reference = $model->external_keys['referenceNumber'] ?? null;
$nested = $config['services']['example']['timeout'] ?? null;
```

## Control Flow

- **Prefer early returns over if/else chains** — guard clauses at the top, happy path unindented
- **Use ternaries** for simple one-expression assignments with two branches
- **Use `match`** for multi-branch value resolution instead of if/elseif/else
- Avoid `else` blocks — if the `if` branch returns, the `else` is unnecessary

```php
// ✅ Early return
if ($request->childId instanceof Optional) {
    return $this->getByParentId($request->parentId);
}

return $this->getByChildId($request->childId);

// ✅ Ternary for simple assignment
$paginator = $isParentScoped
    ? $this->getByParentId($parentId)
    : $this->getByChildId($childId);

// ✅ Match for multi-branch
$label = match ($status) {
    Status::ACTIVE => 'Active',
    Status::PENDING => 'Pending',
    Status::CANCELLED => 'Cancelled',
};

// ❌ Avoid
if ($condition) {
    $result = doA();
} else {
    $result = doB();
}
```

## Casing

- Internal variables and keys: camelCase
- Constants/Enums: UPPER_SNAKE_CASE
- Database columns/tables: snake_case

## Monetary Values / Rates

- **Never store or compute money in a float.** Use `decimal` columns with an explicit scale
  (e.g. `decimal(12, 2)`), and a matching `'decimal:2'` cast on the model.
- Give aggregated monetary columns (sums) **wider precision** than the values they aggregate
  (e.g. `decimal(14, 2)`) — a column sized for one row overflows on a large sum.
- Percentages: store as rates (0–1), not as 0–100, and fix the scale.
- **Decide one project-wide convention for the unit crossing the API boundary** — typically minor
  units (integer cents/pence) over the wire while the database stores major units as decimals —
  and then convert in exactly one place. Mixed conventions are a silent factor-of-100 bug.
- Implement that conversion as a **cast on the Resource DTO**, not ad hoc at call sites. Use
  arbitrary-precision arithmetic (`bcmul($value, '100', 0)`) rather than float multiplication,
  which reintroduces the rounding error the decimal column exists to avoid.


## Primary Keys

- **UUID** (`HasUuids` + `$table->uuid('id')->primary()`): entities exposed in API routes
- **Auto-increment int** (`$table->id()`): internal/lookup/pivot tables never in API URLs

## Models

- PHPDoc `@property` annotations for all properties
- Enum casts via native enums in `casts()` method
- Models contain only: properties, relationships, scopes, factories — no business logic
- Use `#[ObservedBy([Observer::class])]` attribute (not manual registration)
- Relationship return types with PHPDoc generics: `@return BelongsTo<Model, $this>`
- Models with a state machine extend the project's state-model base class rather than reimplementing transition handling

## Enums

- Native backed enums (string or int) with `EnumTrait`
- `EnumTrait` provides: `label()`, `values()`, `tryFromValue()`, `tryFromName()`

## Domain Modules

Code is organised by **domain** under `app/Domains/{Domain}/`, not by technical type.

**List the existing domains before adding one** (`ls app/Domains/`) and place new logic in the
domain that already owns the concept — a new directory per feature fragments a bounded context
and is the most common structural mistake here. Only create a domain when the concept genuinely
has no existing owner.

Top-level directories (`app/Service/`, `app/Models/`, `app/Data/`) are for **cross-cutting
concerns only** — things no single domain owns.

## Routes & Validation

- **Never use `whereUuid()`, `whereNumber()`, or other route constraints** — all validation must happen in the Spatie Data DTO (`#[Uuid]`, `#[Exists]`, etc.). One single source of validation truth.
- When an endpoint takes a **child resource nested under a parent** (e.g. `children/{childId}` under `parents/{parentId}`), validate *ownership* at the DTO level with a scoped-existence rule — i.e. assert the child exists **and** belongs to that parent. Do not rely solely on `firstOrFail()` in the repository: a plain existence check lets a caller read another parent's record by guessing an ID, and the resulting 404-vs-403 difference also leaks which IDs exist.

## DTOs (Spatie Laravel-Data)

Three tiers, scoped by domain under `app/Domains/{Domain}/DTOs/`:

```
DTOs/
  Request/    — Data classes (request input)
  Resource/   — Resource classes (response output)
  Internal/   — Dto classes (internal value objects)
  Rules/      — Domain-specific validation rules
```

Cross-cutting DTOs that don't belong to any domain live in `app/Data/`.

### Data (Request Input)
- Extends `Spatie\LaravelData\Data`, suffixed `Data`, lives in `DTOs/Request/`
- **Must have** `#[MapOutputName(SnakeCaseMapper::class)]`
- Validation via attributes (`#[Required]`, `#[Uuid]`, `#[Exists]`, `#[RequiredWithout]`, `#[In]`, `#[Regex]`, `#[Rule]`, etc.)
- **Prefer attributes over `rules()` method** — use Spatie validation attributes directly on constructor properties wherever possible. Only fall back to a `rules()` override when the validation genuinely cannot be expressed as an attribute. Custom `ValidationRule` classes can be used via `#[Rule(new MyRule)]`.
- Use `#[FromRouteParameter('param')]` for route parameters
- For filter/sort/search endpoints: extend the project's shared query-params Data class, which parses `filter[]`, `sort` and `search` automatically — don't reparse query strings per endpoint
- Where a shared DTO already covers the shape you need (e.g. an endpoint taking only a single route-scoped parent ID), reuse it rather than creating a new single-property DTO

### Resource (Response Output)
- Extends `Spatie\LaravelData\Resource`, suffixed `Resource`, lives in `DTOs/Resource/`
- No case mapping needed when hydrating from Eloquent models. When hydrating from query builder results (snake_case), use `#[MapInputName(SnakeCaseMapper::class)]` to map to camelCase properties.
- No Spatie validation attributes — but PHP types are still enforced (string must be string, int must be int, etc.)
- Use `Optional|type` for nullable fields
- Use `#[WithCast(EnumCast::class, type: MyEnum::class)]` for enums
- Default construction: `::from($model)` for single, `::collect($models)` for collections — Spatie handles mapping automatically
- **Never use `DataCollection::class`** — use `::collect()` directly, which returns an array
- Only add a custom factory method (e.g. `fromDto()`, `fromModel()`) when you need to transform/compute values before construction (e.g. nested DTO → nested Resource). When properties are 1:1, just use `::from()` / `::collect()` directly.
- The Resource owns all mapping logic, not the Use Case
- **Resources must not be passed as input to business logic methods** — use Data DTOs, internal Dto objects, or primitives instead. Resources can be composed into other Resources during response construction.

### Internal (Between components)
- Extends `Spatie\LaravelData\Dto`, suffixed `Dto`, lives in `DTOs/Internal/`
- No validation, no case mapping — typed properties only
- When a method takes 3 or more related parameters, group them into an internal Dto instead of passing loose primitives

## Spatie Gotchas

- **Always use `::from()`, `::collect()`, and other Spatie static methods** — never `new SomeData(...)` directly. `new` bypasses the entire pipeline: validation, casting, transformers, and property mapping are all skipped.
- In custom factory methods, use `self::from([...])` with an array, not `new self(...)`.
- **`Optional` is not nullable**: `Optional|string` throws if accessed when not provided. Use `Optional|string|null` if it can also be genuinely null.
- **`MapInputName` vs `MapOutputName`**: Input maps snake_case→camelCase (from DB). Output maps camelCase→snake_case (to DB). Don't confuse them.
- **`Resource` skips Spatie validation**: `Resource::from()` runs no validation attributes — only transformation. Never use Resource for request input.
- **`PaginatedDataCollection`**: Must pass a paginator object — `::collect($query->jsonPaginate(), PaginatedDataCollection::class)` — not a plain array.
- **`#[FromRouteParameter]`**: Extracts scalar values. For model properties use `#[FromRouteParameterProperty('model', 'property')]`.

## Controllers

- **No logic whatsoever** — accept DTOs, delegate to a UseCase, return `ApiResponse`
- Never inject services into controllers — services are injected into Use Cases
- `ApiResponse::create()` for standard responses
- `ApiResponse::createPaginatedResponse()` for paginated results

## Authorization

Prefer Gates and middleware over Policies. Policies are model-centric, but use cases here receive DTOs rather than models — don't fetch a model purely to satisfy a policy signature.

- **Middleware** — shared authorization checks across a route group (e.g. channel access). Register on the route group so use cases don't duplicate the check.
- **Gates** — use-case-specific authorization driven by scalar values from the DTO (IDs, enums). No model needed. E.g. `Gate::authorize('assign-resource-owner', [$data->parentId, $data->userId])`.
- **Policies** — only when you already have the model loaded and need per-record permissions. Prefer Gates where possible.

## Validation Strategy

Three approaches to validation, each with a distinct purpose. All throw `ValidationException` and return 422 with Laravel's standard error bag format.

### 1. Request DTOs (Spatie Data)
- Validates incoming request payloads (user input)
- Lives in `DTOs/Request/`, extends `Spatie\LaravelData\Data`
- Spatie throws `ValidationException` automatically
- For complex cross-field validation that's still part of the request, use Spatie's `withValidator` method on the Data class — prefer this over creating a separate validator

### 2. ValidateableDto
- Validates **existing data** (from DB, external services) against business requirements
- Lives in `DTOs/Internal/` under the domain, extends `App\Data\ValidateableDto`
- Use `::validate()` when you just need to check validity (throws on failure, returns nothing)
- Use `::validateAndCreate()` when you need the validated object back
- Use when: checking properties of an existing record meet requirements before an action

### 3. Custom Validators
- Standalone `final readonly` validator classes for batch or complex validation
- Use `Validator::make()` and throw `ValidationException` on failure
- Use when: validation involves loops, collecting multiple successes/failures, or logic that doesn't map to a DTO

**Never throw custom domain exceptions with a 422 status code.** 422 means "validation failed" — the frontend expects Laravel's standard validation response shape. If the payload is valid but the system state doesn't allow the action, that's a guard concern (see below).

## Guards

- `final readonly` classes in `app/Domains/{Domain}/Guards/`
- Enforce **business preconditions** — state checks that determine whether an action can proceed
- Throw **domain exceptions** (not `ValidationException`) — mapped to 403, 409, 410 etc. via the exception handler
- Guards answer: "Is the system in a valid state for this action?" — not "Is the input valid?"
- Keep methods focused: one check per method (`ensureEligible`, `ensureNoPendingAuthorisation`, `ensureCanResolve`)
- Guards may inject repositories to query state
- Use cases call guard methods before performing writes

**Guards vs Gates vs Policies:** Gates and Policies check *authorization* (is this user allowed?). Guards check *business preconditions* (is the system state valid?). These are separate concerns.

## Exceptions & HTTP Status Codes

- **Never use `abort()` in Use Cases** — throw domain exceptions or use `Gate::authorize()`
- For business rule violations (missing preconditions, invalid state), throw custom exceptions extending `Exception` (or a base `DomainException`)
- Place domain exceptions in `app/Domains/{Domain}/Exceptions/`
- Domain logic should not know about HTTP status codes — the exception handler maps domain exceptions to HTTP responses

## Concurrency & Distributed Locks

Use the project's distributed-lock helper (built on Laravel's cache locks) for any operation that
must not run concurrently with itself — in particular **non-idempotent writes** that consume
stock, money, or a one-shot external resource.

- Wrap the **whole critical section (check + write)** inside the lock callback. Checking outside
  the lock and writing inside it leaves a TOCTOU gap, which is the bug the lock was meant to
  prevent.
- Scope the lock key to the **specific entity**, e.g. `"resource-authorisation:{$resourceId}"`.
  A global key serialises unrelated work; too broad a key is a throughput problem, too narrow a
  key is a correctness one.
- A zero block time makes a concurrent duplicate request **fail fast**; a non-zero one makes it
  **wait**. Choose deliberately: fail fast for user-initiated double-submits, wait for work that
  must eventually happen.
- Keep the lock TTL short — just long enough for the operation. The TTL is a safety net for a
  crashed holder, so an over-long TTL turns one crash into a long outage for that key.
- Handle the lock-timeout exception explicitly; never let it surface as a 500.

## Use Cases

- `final readonly`, single `handle()` method
- Accepts Data DTO or simple value; returns Resource DTO or `void`
- **Use cases are orchestrators** — they coordinate validation, guards, repositories, and events. They should not contain inline validation logic, eligibility checks, or complex conditionals. Extract these to guards, validators, or services.
- **A use case's `handle()` method should be scannable in 10 seconds.** If you can't read it top-to-bottom and immediately understand the flow (resolve, guard, act, dispatch, return), it has too much logic. No private methods — if the use case needs them, the logic belongs in a guard, service, or repository instead.
- Enforce authorization via `Gate::authorize()` or route middleware — never inline checks with `abort()`
- Inject guards for business precondition checks
- Inject services for complex logic or external calls
- **Do not construct Resources manually** — no `new SomeResource(...)` with field-by-field mapping. Delegate to the Resource's `::from()`, `::collect()`, or a custom factory method

## Services

### External (Manager-Driver pattern)
- Manager + HTTP driver + mock driver, bound to interface
- Driver selected by ENV variable

### Internal
- Complex reusable logic; singleton bindings preferred

## Repositories

- Interface in `Contracts/Repositories/`, implementation in `Repositories/`
- Bind in the **domain's own ServiceProvider** (not a central provider)
- Use `Model::query()->` prefix for all queries (PHPStan-friendly)
- Always use repositories for database access — **mandatory** when interacting with external or legacy database connections
- **Models never leave the repository** — return Resource DTOs or internal Dto objects for reads, IDs or `void` for writes. Never return Data DTOs (those are strictly for request input). Eloquent stays inside the repository boundary.

## Service Providers

- Every domain **must** have its own ServiceProvider at `app/Domains/{Domain}/ServiceProviders/{Domain}ServiceProvider.php`
- Extend `App\Base\BaseServiceProvider`
- Call `$this->loadRoutesFromDirectoryWithSanctum(...)` in `boot()` to auto-register routes with `auth:sanctum` + `api` middleware
- Register all domain singletons (repository contracts, services) in `register()`
- Register in `bootstrap/providers.php`
- Do NOT register domain-specific bindings in `RepositoryServiceProvider`

## Eloquent vs Query Builder

**Hard rule**: List/collection endpoints MUST use `DB::table()` query builder and map results to lightweight DTOs. Do NOT hydrate Eloquent models for list responses.

Non-negotiable for high-volume entities — any table where a single parent record can own hundreds or thousands of rows.

**Eloquent is acceptable for**:
- Single-record reads (`findOrFail`, `first`)
- Creates, updates, deletes — anywhere you need observers, events, state machines, or relationship mutations

If in doubt: returning a collection for display? Query builder. Mutating a single record or needing model behaviour? Eloquent.

### Soft deletes with query builder

`DB::table()` does **not** respect the `SoftDeletes` trait — only Eloquent does. Reads against soft-deletable tables via the query builder will silently return soft-deleted rows unless filtered explicitly.

- Always add `->whereNull('deleted_at')` when reading from a soft-deletable table.
- For joins, filter each soft-deletable table individually, qualifying the column by table name:
  ```php
  DB::table('parents')
      ->join('children', 'parents.id', '=', 'children.parent_id')
      ->whereNull('parents.deleted_at')
      ->whereNull('children.deleted_at')
      ->get();
  ```
- Writes (`insert` / `update` / `delete` / `upsert`) do not need the filter — soft deletes only affect reads.
- Aggregations (`sum`, `count`, `avg`, etc.) are reads — they need the filter too. A missing `whereNull('deleted_at')` on a `sum()` silently inflates totals, and because the number still looks plausible, nothing surfaces the error.

**Check the migration for a `deleted_at` column before deciding.** Default to assuming a table is
soft-deletable and add the filter; a redundant `whereNull` on a table without the column fails
loudly at query time, whereas a missing one fails silently with wrong data.

### High-volume read patterns

Identify the highest-volume child tables in the schema — the ones where no parent scope reliably
bounds the row count — and treat every read against them as a potential performance cliff. For
all new work touching such a table:

1. **Never hydrate a collection of models.** Use the query builder for any multi-row read.
   Single-record hydration (`findOrFail`, `first`) is fine where you need observers, state
   machines, or relationships.
2. **List endpoints must paginate.** No unbounded `->get()` / `->all()` returning a list to the
   caller — use `jsonPaginate` or equivalent.
3. **Prefer pre-computed aggregates over live counts.** Where the schema maintains an aggregate
   table or counter column, headline totals must read from it. Reserve live `withCount` /
   `count()` for filtered subsets where no aggregate exists.
4. **Never `whereIn('id', [...])` with an unbounded ID list.** If the driving set can grow,
   invert the query — join or subquery back to the source table instead of materialising IDs in
   PHP, which pushes an unbounded list into the SQL string.

## State Machines (Spatie Model States)

- Record-locking pattern: a `locked_state` column plus a per-model middleware that rejects writes to a locked record, registered on the route group rather than checked in each use case
- Write attempts on a locked record return **HTTP 423 Locked**

## Events & Listeners

- Events: `final readonly` value objects with `Dispatchable` trait
- Listeners: `final readonly`, implement `ShouldQueue` for async
- Subscriber pattern for grouping related listeners — register in `EventServiceProvider::$subscribe`

## Exception Handling

- A custom handler routes exceptions to specialized per-category handlers
- Each handler extends a shared base and persists the failure to the project's exception log
- All responses are JSON and carry a **unique exception ID**, so a user-reported error can be traced to one log entry without exposing internals in the response

## Testing

- Base classes: `TestCaseFeature` (with DB), `TestCaseUnit` (no DB), `TestCaseIntegration`
- Use `#[Test]` and `#[CoversClass(...)]` attributes
- Every endpoint/service/workflow needs at least one feature test
- Do NOT write tests that only verify framework/Eloquent behaviour
- Use `Sanctum::actingAs($user)` for auth in tests
- Use factories with states for test data
