Scaffold a complete backend vertical slice in this project.

**Arguments**: `$ARGUMENTS` = Domain name (PascalCase), Endpoint name (PascalCase), HTTP method (get/post/patch/delete)

If arguments are missing, ask the user.

## Before generating

1. Confirm the domain exists under `app/Domains/`. If not, suggest running `/backend:new-domain` first.
2. Read existing files in the target domain to match local naming and style.
3. Ask the user what the endpoint does — what data it accepts, what it returns, and what business logic is involved.

## Architecture Rules

- **Controller**: No logic. Injects UseCase via constructor. Accepts Data DTO as parameter. Returns `ApiResponse::create()`.
- **UseCase**: `final readonly` class. Single `handle()` method. Accepts Data DTO or simple value. Returns Resource DTO or void. Inject repository contracts and services via constructor.
- **Repository**: Always use for new database access. Interface in `Contracts/Repositories/`, implementation in `Repositories/`. Bind in the domain's ServiceProvider.
- **Route**: Add to existing domain route file. Kebab-case URLs. Use `->can(Permissions::NAME->value)` for permissions. Route names follow `domain.resource.action` pattern.
- **Test**: Extends `TestCaseFeature`. Use `#[Test]` attribute. Test happy path AND validation failures. Use route names not hardcoded URLs. `$this->user` and `$this->user->organization` are available from the Authenticated trait.

## DTO Conventions

Three types of DTO, all using Spatie Laravel-Data, all living in `app/Domains/{Domain}/DTOs/`:

### Data DTO (Request Input) — for post/patch/put

Extends `Spatie\LaravelData\Data`. Suffixed with `Data`.

**Must have** `#[MapOutputName(SnakeCaseMapper::class)]`.

All validation via attributes — NOT separate Request classes.

```php
#[MapOutputName(SnakeCaseMapper::class)]
class {Name}Data extends Data
{
    public function __construct(
        #[FromRouteParameter('organizationId'), Required, Uuid, Exists('organizations', 'id')]
        public string $organizationId,
        // Body params with validation attributes
    ) {}
}
```

### Resource DTO (Response Output)

Extends `Spatie\LaravelData\Resource`. Suffixed with `Resource`.

No case mapping — outputs camelCase to frontend. Default construction via `::from($model)` or `::collect($models)`.

```php
class {Name}Resource extends Resource
{
    public function __construct(
        // Properties matching API response shape
    ) {}
}
```

### Internal DTO (Between components)

Extends `Spatie\LaravelData\Dto`. Suffixed with `Dto`. Lives in `DTOs/Internal/`.

No validation, no case mapping — typed properties only.

## Other Templates

### UseCase

`app/Domains/{Domain}/UseCases/{Name}UseCase.php`

```php
final readonly class {Name}UseCase
{
    public function __construct(
        private {RepositoryContract} $repository,
    ) {}

    public function handle({Name}Data $data): {Name}Resource
    {
        // Business logic
    }
}
```

### Controller

Add to existing `app/Domains/{Domain}/Controllers/{Domain}Controller.php` or create new:

```php
class {Domain}Controller extends Controller
{
    public function __construct(
        private readonly {Name}UseCase $useCase,
    ) {}

    public function methodName({Name}Data $data): JsonResponse
    {
        $response = $this->useCase->handle($data);

        return ApiResponse::create($response);
    }
}
```

### Route

Add to existing `app/Domains/{Domain}/Routes/{file}.php`:

```php
Route::controller({Domain}Controller::class)
    ->prefix('{resource-prefix}')
    ->middleware({TenantScopeAccessMiddleware}::class)
    ->name('{route.name.prefix}.')
    ->group(function () {
        Route::{method}('/{path}', 'methodName')
            ->name('{action}')
            ->can(Permissions::PERMISSION_NAME->value);
    });
```

Register the tenant-scope access middleware on the **route group**, not per use case — a scoped
route that silently omits it is the standard way cross-tenant data leaks. Use the project's
existing middleware; check how sibling route files in the domain do it.

### Feature Test

`tests/{Domain}/{Name}Test.php`

```php
class {Name}Test extends TestCaseFeature
{
    #[Test]
    public function it_returns_expected_response(): void
    {
        $response = $this->{method}Json(route('{route.name}', [
            'organizationId' => $this->user->organization->id,
        ]), $payload);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                // Expected fields
            ],
        ]);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->{method}Json(route('{route.name}', [
            'organizationId' => $this->user->organization->id,
        ]));

        $response->assertStatus(422);
    }
}
```

## After Scaffolding

1. List all created files
2. Ask if they want to run `/backend:check-backend` to verify
