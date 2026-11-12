Scaffold a service in this project.

**Arguments**: `$ARGUMENTS` = Domain name (PascalCase), Service name (PascalCase, e.g. `Provisioning`, `Notification`)

If arguments are missing, ask the user.

## Before generating

1. Ask the user: **What does this service interact with?**
2. Based on the answer, determine the type:
   - **External API** (third-party service, another system) → Manager-Driver pattern
   - **Needs a mock driver** (real implementation not ready yet) → Manager-Driver pattern
   - **Cross-domain orchestration or complex reusable logic** → Simple internal service
3. **Always ask before using the Manager-Driver pattern.**

---

## Option A: Manager-Driver Pattern

Use when the service calls external APIs or needs mock/real driver swapping.

### 1. Contract

`app/Service/{Name}/Contracts/{Name}ServiceContract.php`

```php
interface {Name}ServiceContract
{
    // Define methods the drivers must implement
}
```

### 2. Manager

`app/Service/{Name}/{Name}ServiceManager.php`

```php
class {Name}ServiceManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('{name}.driver');
    }

    public function createHttpDriver(): {Name}ServiceContract
    {
        return new {Name}ServiceHttp(
            config('{name}.base_url'),
        );
    }

    public function createMockDriver(): {Name}ServiceContract
    {
        return new {Name}ServiceMocked();
    }
}
```

### 3. HTTP Driver

`app/Service/{Name}/{Name}ServiceHttp.php` — `final readonly`, implements contract, makes real HTTP calls, maps responses to internal DTOs.

### 4. Mock Driver

`app/Service/{Name}/{Name}ServiceMocked.php` — `final readonly`, implements contract, returns hardcoded realistic test data.

### 5. Config File

`config/{name}.php` — driver ENV variable defaulting to `'mock'`, base_url, other config.

### 6. ServiceProvider

`app/Providers/{Name}ServiceProvider.php`

```php
class {Name}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind({Name}ServiceContract::class, function (Container $container) {
            return (new {Name}ServiceManager($container))->driver();
        });
    }
}
```

Register in `bootstrap/providers.php`.

---

## Option B: Simple Internal Service

Use for cross-domain orchestration or complex reusable logic.

`app/Domains/{Domain}/Services/{Name}Service.php`

```php
final readonly class {Name}Service
{
    public function __construct(
        // Inject repository contracts, other services
    ) {}

    // Public methods for the logic this service encapsulates
}
```

- `final readonly` class
- Bind as singleton in the domain's ServiceProvider if shared
- No contract needed unless multiple implementations expected

---

## After Scaffolding

1. List all created files
2. For Manager-Driver: remind user to add ENV variables and register the ServiceProvider
3. For simple service: remind user to add singleton binding to domain ServiceProvider if needed
