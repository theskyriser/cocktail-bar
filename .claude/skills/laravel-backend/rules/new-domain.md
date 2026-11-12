Scaffold a new domain in this project.

**Arguments**: `$ARGUMENTS` = Domain name (PascalCase, e.g. `Billing`, `Inventory`)

If no argument provided, ask the user.

## Directory Structure

```
app/Domains/{DomainName}/
├── Contracts/
│   └── Repositories/
├── Controllers/
├── DTOs/
│   └── Internal/
├── Enums/
├── Models/
├── Repositories/
├── Routes/
├── ServiceProviders/
└── UseCases/
```

## Files to Generate

### ServiceProvider

`app/Domains/{DomainName}/ServiceProviders/{DomainName}ServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Domains\{DomainName}\ServiceProviders;

use App\Base\BaseServiceProvider;

final class {DomainName}ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFromDirectoryWithSanctum('app/Domains/{DomainName}/Routes');
    }

    public function register(): void
    {
        // Bind repository contracts to implementations here
    }
}
```

### Route file

`app/Domains/{DomainName}/Routes/{domainNameKebab}.php`

```php
<?php

use Illuminate\Support\Facades\Route;

// Routes for {DomainName} domain
// Loaded by {DomainName}ServiceProvider with sanctum middleware
```

Create the directories and these two files. Nothing else.

## After Scaffolding

Tell the user:

1. **Register the ServiceProvider** — Add to `bootstrap/providers.php`:
   ```php
   App\Domains\{DomainName}\ServiceProviders\{DomainName}ServiceProvider::class,
   ```
2. Use `/backend:new-endpoint` to add endpoints to this domain

Do NOT automatically register the provider.
