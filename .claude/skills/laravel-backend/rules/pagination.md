---
paths:
  - "**/*.php"
---

# Backend Pagination

The project uses **Spatie JSON API Paginate** package for pagination, following JSON:API specification.

## Query Parameters

Pagination uses `page[number]` and `page[size]` format (NOT `page` and `perPage`):

```
GET /api/organizations/{id}/contacts?page[number]=2&page[size]=20&searchTerm=john
```

**Configuration** (`config/json-api-paginate.php`):

- `page[number]` - Page number (default: 1)
- `page[size]` - Items per page (default: 30, max: 500)

## Implementation Pattern

### 1. Data Class (Request DTO)

Extend `QueryParamsData`. **DO NOT** add `page` or `perPage` properties - pagination is handled automatically by `jsonPaginate()`.

```php
// ✅ CORRECT
class GetContactsData extends QueryParamsData
{
    public function __construct(
        #[FromRouteParameter('organizationId'), Required, Uuid, Exists('organizations', 'id')]
        public string $organizationId,
        public string|Optional $searchTerm,
    ) {}
}

// ❌ WRONG - Will cause "page field must be a number" error
class GetContactsData extends QueryParamsData
{
    public function __construct(
        public string $organizationId,
        public int|Optional $page,      // DON'T DO THIS
        public int|Optional $perPage,   // DON'T DO THIS
    ) {}
}
```

### 2. Use Case

Use `jsonPaginate()` macro and return `PaginatedDataCollection`:

```php
public function handle(GetContactsData $data): PaginatedDataCollection
{
    $contacts = $this->buildQuery($data)->jsonPaginate();

    return ContactResource::collect($contacts, PaginatedDataCollection::class);
}
```

### 3. Controller

Return using `ApiResponse::createPaginatedResponse()`:

```php
public function paginated(GetContactsData $data): JsonResponse
{
    return ApiResponse::createPaginatedResponse(
        $this->useCase->handle($data)
    );
}
```

### 4. Route

```php
Route::get('/paginated', [ContactController::class, 'paginated'])
    ->name('paginated');
```

## Response Format

```json
{
  "data": [
    { "id": "uuid-1", "name": "John" },
    { "id": "uuid-2", "name": "Jane" }
  ],
  "meta": {
    "currentPage": 2,
    "perPage": 20,
    "total": 150,
    "lastPage": 8,
    "from": 21,
    "to": 40,
    "firstPageUrl": "...?page[number]=1&page[size]=20",
    "lastPageUrl": "...?page[number]=8&page[size]=20",
    "nextPageUrl": "...?page[number]=3&page[size]=20",
    "prevPageUrl": "...?page[number]=1&page[size]=20",
    "path": "http://localhost/api/..."
  }
}
```

## Testing Pagination

```php
#[Test]
public function it_returns_paginated_contacts(): void
{
    $response = $this->actingAs($user)->getJson(route('organizations.contacts.paginated', [
        'organizationId' => $organization->id,
        'page' => ['number' => 2, 'size' => 10],
    ]));

    $response->assertOk();
    $response->assertJsonPath('meta.currentPage', 2);
    $response->assertJsonPath('meta.perPage', 10);
    $response->assertJsonCount(10, 'data');
}
```

## Common Mistakes

| Mistake                                        | Error                         | Fix                                                     |
| ---------------------------------------------- | ----------------------------- | ------------------------------------------------------- |
| Adding `page`/`perPage` to Data class          | "page field must be a number" | Remove these properties, let `jsonPaginate()` handle it |
| Using `paginate()` instead of `jsonPaginate()` | Wrong parameter format        | Use `jsonPaginate()` macro                              |
| Returning raw paginator                        | Missing meta format           | Use `ApiResponse::createPaginatedResponse()`            |

## Custom Pagination with Additional Data

Use `CustomPaginatedDataCollection` to add extra meta data:

```php
$collection = ContactResource::collect($contacts, CustomPaginatedDataCollection::class);
$collection->withAdditionalData(['totalValue' => $totalValue]);

return ApiResponse::createPaginatedResponse($collection);
```
