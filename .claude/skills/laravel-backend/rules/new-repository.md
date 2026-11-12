Scaffold a repository in this project.

**Arguments**: `$ARGUMENTS` = Domain name (PascalCase), Repository name (PascalCase, e.g. `Invoice`, `Contact`)

If arguments are missing, ask the user.

## Before generating

1. Confirm the domain exists under `app/Domains/`.
2. Read existing repositories in the domain to match local style.
3. Ask the user what queries the repository needs to support.

## Files to Generate

### 1. Contract

`app/Domains/{Domain}/Contracts/Repositories/{Name}RepositoryContract.php`

```php
<?php

declare(strict_types=1);

namespace App\Domains\{Domain}\Contracts\Repositories;

interface {Name}RepositoryContract
{
    // Define public methods — accept and return DTOs, never raw models
    // Use PHPDoc for Collection generics:
    // /** @return Collection<int, {Name}Resource> */
}
```

Key rules:
- Keep interfaces small and intention-revealing
- Methods accept Data DTOs, Internal DTOs, or simple values
- Methods return Resource DTOs, Internal DTOs, Collections of DTOs, PaginatedDataCollection, or void
- Never expose raw Eloquent models or Query Builders in the contract
- Use full PHPDoc with generics for Collection return types

### 2. Implementation

`app/Domains/{Domain}/Repositories/{Name}Repository.php`

```php
<?php

declare(strict_types=1);

namespace App\Domains\{Domain}\Repositories;

use App\Domains\{Domain}\Contracts\Repositories\{Name}RepositoryContract;

final readonly class {Name}Repository implements {Name}RepositoryContract
{
    public function __construct(
        // Inject dependencies
    ) {}

    // Implement contract methods
    // Use Model::query()-> prefix for all queries
    // Return DTOs, not models
}
```

### 3. ServiceProvider Binding

Add to the domain's existing ServiceProvider `register()` method:

```php
$this->app->singleton({Name}RepositoryContract::class, {Name}Repository::class);
```

## After Scaffolding

1. List all created/modified files
2. Remind user to import the contract and implementation in the ServiceProvider if newly added
