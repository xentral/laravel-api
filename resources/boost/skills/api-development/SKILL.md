---
name: api-development
description: Build new API endpoints and how to test them properly
---

## Resource Definition

Extend `Xentral\LaravelApi\Http\ApiResource` with an `OA\Schema` attribute. Always set `additionalProperties: false`, specify `schema`, `required`, `properties`, and `type`.

```php
use Xentral\LaravelApi\Http\ApiResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SalesOrder',
    required: ['id', 'status', 'customer', 'createdAt'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', ref: SalesOrderStatus::class),
        new OA\Property(property: 'customer', anyOf: [
            new OA\Schema(ref: CustomerResource::class),
            new OA\Schema(
                properties: [new OA\Property(property: 'id', type: 'integer')],
                type: 'object'
            ),
        ]),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
    ],
    type: 'object',
    additionalProperties: false,
)]
class SalesOrderResource extends ApiResource
{
    /** @var SalesOrder */
    public $resource;

    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status,
            'customer' => $this->includeWhenLoaded('customer', CustomerResource::class),
            'createdAt' => $this->resource->created_at->toAtomString(),
        ];
    }
}
```

### Include Patterns Comparison

Use the right helper depending on the desired behavior:

```php
// includeWhenLoaded: Full resource when loaded, reference fallback {'id': foreignKey}
'customer' => $this->includeWhenLoaded('customer', CustomerResource::class),

// whenLoaded: Full resource when loaded, custom fallback
'customer' => $this->whenLoaded(
    'customer',
    fn() => new CustomerResource($this->resource->customer),
    ['id' => $this->resource->customer_id]
),

// reference: Always returns {'id': foreignKey} or null
'customer' => $this->reference('customer'),
```

### DummyInclude for Virtual/Computed Includes

Use `DummyInclude` when a nested include doesn't map to a real database relationship (e.g., computed fields appended to a relation):

```php
use Xentral\LaravelApi\Query\DummyInclude;

// In controller — DummyInclude::make() auto-loads the parent relationship
$query->allowedIncludes(['customer', 'lineItems', DummyInclude::make('lineItems.customFields')]);
```

### Deprecation with Sunset Header

Use `deprecatedSince()` to add a `Sunset` HTTP header to responses:

```php
public function view(int $id): SalesOrderResource
{
    $order = SalesOrder::findOrFail($id);

    return (new SalesOrderResource($order))
        ->deprecatedSince(new \DateTimeImmutable('2025-06-01'));
}
```

## Request Bodies (Validation)

Use FormRequest or spatie/laravel-data Data objects. Schema `required` fields and Laravel validation rules must match. Always set `additionalProperties: false`.

```php
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSalesOrderRequest',
    required: ['customer', 'positions'],
    properties: [
        new OA\Property(
            property: 'customer',
            type: 'object',
            required: ['id'],
            properties: [new OA\Property(property: 'id', type: 'integer')]
        ),
        new OA\Property(
            property: 'positions',
            type: 'array',
            items: new OA\Items(
                required: ['sku', 'quantity'],
                properties: [
                    new OA\Property(property: 'sku', type: 'string'),
                    new OA\Property(property: 'quantity', type: 'integer'),
                ]
            )
        ),
    ],
    type: 'object',
    additionalProperties: false,
)]
class CreateSalesOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer.id' => ['required', 'integer', 'exists:customers,id'],
            'positions' => ['required', 'array'],
            'positions.*.sku' => ['required', 'string'],
            'positions.*.quantity' => ['required', 'integer'],
        ];
    }
}
```

## Controller Endpoints

### List Endpoint

Use `#[ListEndpoint]` for paginated collection endpoints. Always use `QueryBuilder::for()` and wrap filters in `FilterParameter`.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Filters\{FilterParameter, IdFilter, StringFilter, DateFilter, EnumFilter};
use Xentral\LaravelApi\OpenApi\{QuerySort, PaginationType};
use Xentral\LaravelApi\Query\{QueryBuilder, Filters\QueryFilter};

#[ListEndpoint(
    path: '/api/v1/sales-orders',
    resource: SalesOrderResource::class,
    description: 'Paginated list of sales orders',
    includes: ['customer', 'positions'],
    parameters: [
        new FilterParameter([
            new IdFilter,
            new StringFilter(name: 'documentNumber'),
            new EnumFilter(name: 'status', enumSource: SalesOrderStatus::class),
            new DateFilter(name: 'documentDate'),
            new DateFilter(name: 'createdAt'),
            new DateFilter(name: 'updatedAt'),
        ]),
        new QuerySort(['created_at', 'updated_at']),
    ],
    paginationType: [PaginationType::SIMPLE, PaginationType::TABLE],
)]
public function index(): ResourceCollection
{
    $orders = QueryBuilder::for(SalesOrder::class)
        ->defaultSort('-created_at')
        ->allowedFilters([
            QueryFilter::identifier(),
            QueryFilter::string('documentNumber', 'document_number'),
            QueryFilter::string('status', enum: SalesOrderStatus::class),
            QueryFilter::date('documentDate', 'datum'),
            QueryFilter::date('createdAt', 'created_at'),
            QueryFilter::date('updatedAt', 'updated_at'),
        ])
        ->allowedSorts(['created_at', 'updated_at'])
        ->allowedIncludes(['customer', 'positions'])
        ->apiPaginate(100, PaginationType::SIMPLE, PaginationType::TABLE);

    return SalesOrderResource::collection($orders);
}
```

### Get Endpoint

Use `#[GetEndpoint]` for single resource retrieval.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;

#[GetEndpoint(
    path: '/api/v1/sales-orders/{id}',
    resource: SalesOrderResource::class,
    description: 'View a single sales order',
    tags: ['SalesOrder'],
    includes: ['customer', 'positions'],
)]
public function view(int $id): SalesOrderResource
{
    $order = QueryBuilder::for(SalesOrder::class)
        ->allowedIncludes(['customer', 'positions'])
        ->findOrFail($id);

    return new SalesOrderResource($order);
}
```

### Post Endpoint

Use `#[PostEndpoint]` with `successStatus: '201'`.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\PostEndpoint;

#[PostEndpoint(
    path: '/api/v1/sales-orders',
    request: CreateSalesOrderRequest::class,
    resource: SalesOrderResource::class,
    description: 'Create a new sales order',
    tags: ['SalesOrder'],
    successStatus: '201',
)]
public function create(CreateSalesOrderRequest $request): SalesOrderResource
{
    $order = SalesOrder::create($request->validated());
    return new SalesOrderResource($order);
}
```

### Patch Endpoint

Use `#[PatchEndpoint]` or `#[PutEndpoint]` for updates.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\PatchEndpoint;

#[PatchEndpoint(
    path: '/api/v1/sales-orders/{id}',
    request: UpdateSalesOrderRequest::class,
    resource: SalesOrderResource::class,
    description: 'Update an existing sales order',
    tags: ['SalesOrder'],
)]
public function update(UpdateSalesOrderRequest $request, int $id): SalesOrderResource
{
    $order = SalesOrder::findOrFail($id);
    $order->update($request->validated());
    return new SalesOrderResource($order);
}
```

### Delete Endpoint

Use `#[DeleteEndpoint]` with `validates` for conditional deletion messages.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\DeleteEndpoint;

#[DeleteEndpoint(
    path: '/api/v1/sales-orders/{id}',
    description: 'Delete a sales order',
    tags: ['SalesOrder'],
    validates: ['status' => 'Only pending sales orders can be deleted.'],
)]
public function delete(int $id): Response
{
    $order = SalesOrder::findOrFail($id);

    if ($order->status !== SalesOrderStatus::PENDING) {
        throw ValidationException::withMessages([
            'status' => 'Only pending sales orders can be deleted.',
        ]);
    }

    $order->delete();
    return response()->noContent();
}
```

### Action Endpoint

Use `#[ActionEndpoint]` for custom state-changing actions. `ActionEndpoint` extends `PatchEndpoint`. Path pattern: `/resources/{id}/actions/{action}`.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\ActionEndpoint;

#[ActionEndpoint(
    path: '/api/v1/sales-orders/{id}/actions/approve',
    resource: SalesOrderResource::class,
    description: 'Approve a sales order',
    tags: ['SalesOrder'],
)]
public function approve(int $id): SalesOrderResource
{
    $order = SalesOrder::findOrFail($id);
    $order->update(['status' => SalesOrderStatus::APPROVED]);
    return new SalesOrderResource($order);
}
```

## OpenAPI Filter Attributes

Wrap typed filter attributes inside `FilterParameter` in the endpoint's `parameters` array.

```php
parameters: [
    new FilterParameter([
        new IdFilter,                                                    // default name: 'id'
        new IdFilter(name: 'customerId'),                                // custom name
        new StringFilter(name: 'name'),
        new NumberFilter(name: 'totalAmount'),
        new DateFilter(name: 'documentDate'),
        new DateTimeFilter(name: 'createdAt'),
        new EnumFilter(name: 'status', enumSource: StatusEnum::class),   // BackedEnum class
        new EnumFilter(name: 'type', enumSource: ['a', 'b', 'c']),      // array of values
        new BooleanFilter(name: 'isActive'),
    ]),
]
```

All filter classes live in `Xentral\LaravelApi\OpenApi\Filters\` and extend `FilterProperty`.

## Operator Reference Table

| Filter Type | Operators |
|---|---|
| `IdFilter` | equals, notEquals, in, notIn |
| `StringFilter` | equals, notEquals, in, notIn, contains, notContains, startsWith, endsWith |
| `NumberFilter` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals |
| `DateFilter` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals |
| `DateTimeFilter` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals, isNull, isNotNull |
| `EnumFilter` | equals, notEquals, in, notIn |
| `BooleanFilter` | equals, notEquals |

**QueryBuilder layer operators** (may include additional operators not exposed in OpenAPI):

| QueryFilter Method | Operators |
|---|---|
| `QueryFilter::identifier()` | equals, notEquals, in, notIn, isNull, isNotNull |
| `QueryFilter::string()` | equals, notEquals, in, notIn, contains, notContains, startsWith, endsWith, isNull, isNotNull |
| `QueryFilter::number()` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals, isNull, isNotNull |
| `QueryFilter::date()` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals, isNull, isNotNull |
| `QueryFilter::datetime()` | equals, notEquals, lessThan, lessThanOrEquals, greaterThan, greaterThanOrEquals, isNull, isNotNull |
| `QueryFilter::boolean()` | equals, notEquals |
| `QueryFilter::booleanInteger()` | equals, notEquals |

## OpenAPI-to-QueryFilter Mapping Table

Both layers must be declared for every filter. Use this mapping:

| OpenAPI Filter | QueryFilter Method | Notes |
|---|---|---|
| `IdFilter` | `QueryFilter::identifier()` | Default name `'id'` on both sides |
| `IdFilter(name: 'customerId')` | `QueryFilter::identifier('customerId', 'customer_id')` | Custom name with column mapping |
| `StringFilter(name: 'name')` | `QueryFilter::string('name')` | |
| `NumberFilter(name: 'total')` | `QueryFilter::number('total', 'total_amount')` | Second param maps to DB column |
| `DateFilter(name: 'issuedAt')` | `QueryFilter::date('issuedAt', 'issued_at')` | |
| `DateTimeFilter(name: 'createdAt')` | `QueryFilter::datetime('createdAt', 'created_at')` | |
| `EnumFilter(name: 'status', enumSource: E::class)` | `QueryFilter::string('status', enum: E::class)` | Pass enum class to both |
| `BooleanFilter(name: 'isActive')` | `QueryFilter::boolean('isActive', 'is_active')` | |

## Sorting

Use `QuerySort` in the endpoint attribute and `allowedSorts()` + `defaultSort()` on the QueryBuilder.

```php
// OpenAPI layer — in endpoint parameters
parameters: [
    new QuerySort(['created_at', 'updated_at', 'total_amount'], default: '-created_at'),
]

// QueryBuilder layer
$query->defaultSort('-created_at')
    ->allowedSorts(['created_at', 'updated_at', 'total_amount']);
```

**URL format**: `?sort=created_at` (ascending) or `?sort=-created_at` (descending). Multiple sorts: `?sort=-created_at,total_amount`.

## Pagination

### `apiPaginate()` Signature

```php
public function apiPaginate(
    int $maxPageSize = 100,
    PaginationType ...$allowedTypes
): Paginator|LengthAwarePaginator|CursorPaginator
```

### Pagination Types

| Type | Description | Use Case |
|---|---|---|
| `PaginationType::SIMPLE` | Basic prev/next links | Most efficient, default |
| `PaginationType::TABLE` | Full pagination with page numbers and totals | UI tables with page counts |
| `PaginationType::CURSOR` | Cursor-based pagination | Large/real-time datasets |

### Client Control

- **Page size**: `?page[size]=25` (max enforced by `$maxPageSize` parameter)
- **Page number**: `?page[number]=3` (for SIMPLE and TABLE)
- **Pagination type**: `X-Pagination` header (`simple`, `table`, `cursor`) — client selects from the allowed types
- **Response field casing**: Configured via `schemas.default.config.pagination_response.casing` (`'snake'` or `'camel'`)

Endpoint attribute and `apiPaginate()` must declare matching types:

```php
// Endpoint attribute
paginationType: [PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR],

// Controller
->apiPaginate(100, PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR)
```

## Content Negotiation

Use `additionalMediaTypes` on `GetEndpoint` to support multiple response formats:

```php
use Xentral\LaravelApi\OpenApi\Responses\PdfMediaType;

#[GetEndpoint(
    path: '/api/v1/invoices/{id}',
    resource: InvoiceResource::class,
    description: 'Get invoice',
    additionalMediaTypes: [new PdfMediaType],
)]
public function show(Request $request, int $id): InvoiceResource|Response
{
    $invoice = Invoice::findOrFail($id);

    if ($request->header('Accept') === 'application/pdf') {
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice.pdf"',
        ]);
    }

    return new InvoiceResource($invoice);
}
```

## Deprecation & Sunset

### Endpoint-Level Deprecation

Pass a `DateTimeInterface` to the `deprecated` parameter on any endpoint attribute:

```php
#[GetEndpoint(
    path: '/api/v1/legacy-orders/{id}',
    resource: OrderResource::class,
    description: 'Get order (deprecated)',
    deprecated: new \DateTimeImmutable('2025-01-15'),
)]
```

This marks the endpoint as `deprecated: true` in the OpenAPI spec and records the deprecation date in the `x-deprecated_on` extension.

### Resource-Level Sunset Header

Use `deprecatedSince()` on `ApiResource` instances to add a `Sunset` HTTP header (see Resource Definition section).

### Deprecation Filter Config

```php
// config/openapi.php
'deprecation_filter' => [
    'enabled' => true,
    'months_before_removal' => 6,  // endpoints deprecated > 6 months are removed from spec
],
```

## Feature Flags, Scopes, and Problems

All endpoint attributes accept these optional parameters:

```php
#[PatchEndpoint(
    path: '/api/v1/orders/{id}',
    resource: OrderResource::class,
    description: 'Update order',
    featureFlag: 'order-editing',           // string or BackedEnum — adds feature flag notice to description
    scopes: ['orders.write', 'admin'],      // string or array — recorded in x-scopes extension
    problems: ['conflict'],                 // array — references problems defined in config/openapi.php
    isInternal: true,                       // marks endpoint as internal via x-internal extension
)]
```

### Problems Config

Define reusable problem responses in `config/openapi.php`:

```php
'problems' => [
    'conflict' => [
        'status' => 409,
        'body' => [
            'type' => 'https://api.example.com/problems/conflict',
            'title' => 'Conflict happened',
        ],
    ],
],
```

## Custom Filter Collections

Reuse filters across endpoints by implementing `FilterSpecCollection`:

```php
use Xentral\LaravelApi\OpenApi\Filters\{FilterProperty, FilterSpecCollection};

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SalesOrderFilters implements FilterSpecCollection
{
    public function getFilterSpecification(): array
    {
        return [
            new IdFilter,
            new StringFilter(name: 'documentNumber'),
            new DateFilter(name: 'documentDate'),
        ];
    }
}

// Usage in endpoint
parameters: [new FilterParameter([new SalesOrderFilters(), new QuerySort(['created_at'])])]
```

For the QueryBuilder layer, implement `QueryBuilderFilterCollection`:

```php
use Xentral\LaravelApi\Query\Filters\QueryBuilderFilterCollection;

class SalesOrderQueryFilters implements QueryBuilderFilterCollection
{
    public function getFilters(): array
    {
        return [
            QueryFilter::identifier(),
            QueryFilter::string('documentNumber', 'document_number'),
            QueryFilter::date('documentDate', 'datum'),
        ];
    }
}

// Usage in controller
$query->allowedFilters(new SalesOrderQueryFilters());
```

## Filter URL Format

```
# Single filter
/api/v1/orders?filter[0][key]=documentNumber&filter[0][op]=equals&filter[0][value]=INV-001

# Comparison operator
/api/v1/orders?filter[0][key]=documentDate&filter[0][op]=lessThan&filter[0][value]=2025-05-05

# Multi-value (in operator)
/api/v1/orders?filter[0][key]=status&filter[0][op]=in&filter[0][value][]=pending&filter[0][value][]=approved

# Multiple filters
/api/v1/orders?filter[0][key]=status&filter[0][op]=equals&filter[0][value]=pending&filter[1][key]=documentDate&filter[1][op]=greaterThan&filter[1][value]=2025-01-01
```

## Configuration Reference

Publish config: `php artisan vendor:publish --provider="Xentral\LaravelApi\ApiServiceProvider"`

```php
// config/openapi.php
return [
    'docs' => [
        'enabled' => env('APP_ENV') !== 'production',  // enable web docs interface
        'prefix' => 'api-docs',                         // URL prefix
        'middleware' => ['web', 'auth'],                 // middleware for docs routes
        'client' => 'swagger',                          // 'swagger' or 'scalar'
    ],
    'problems' => [
        // Reusable problem responses referenced by endpoint 'problems' param
        'conflict' => ['status' => 409, 'body' => [...]],
    ],
    'schemas' => [
        'default' => [
            'client' => null,                           // per-schema client override
            'config' => [
                'oas_version' => '3.1.0',
                'folders' => [base_path('app')],        // directories to scan for attributes
                'output' => base_path('openapi.yml'),   // output file path
                'pagination_response' => [
                    'casing' => 'snake',                // 'snake' or 'camel'
                ],
                'validation_response' => [
                    'status_code' => 422,
                    'content_type' => 'application/json',
                    'max_errors' => 3,
                    'content' => [
                        'message' => 'The given data was invalid.',
                        'errors' => '{{errors}}',
                    ],
                ],
                'deprecation_filter' => [
                    'enabled' => true,
                    'months_before_removal' => 6,       // auto-remove from spec after N months
                ],
                'feature_flags' => [
                    'description_prefix' => "This endpoint is only available if the feature flag `{flag}` is enabled.\n\n",
                ],
                'rate_limit_response' => [
                    'enabled' => true,
                    'message' => 'Too Many Requests',
                ],
                'validation_commands' => [],             // artisan commands to run during validation
            ],
            'info' => [
                'name' => 'My API',
                'version' => '1.0.0',
                'description' => 'Developer API',
                'contact' => ['name' => '...', 'url' => '...', 'email' => '...'],
                'servers' => [['url' => '...', 'description' => '...']],
            ],
        ],
    ],
];
```

## Generating OpenAPI Schema

```bash
php artisan openapi:generate          # Generate default schema
php artisan openapi:generate v1       # Generate specific schema
```

## Important Rules

1. **Always use QueryBuilder**: Use `Xentral\LaravelApi\Query\QueryBuilder::for()`, never plain Eloquent
2. **Match validation to schema**: OpenAPI schema `required` fields must match Laravel validation rules
3. **Set additionalProperties false**: Always add `additionalProperties: false` on schemas
4. **Use typed resources**: Add `/** @var ModelName */ public $resource;` for IDE support
5. **Date formatting**: Use `->toAtomString()` for datetime, `->toDateString()` for date-only
6. **Includes pattern**: Use `includeWhenLoaded()` for relations that should show reference ID when not loaded
7. **Includes pattern**: Use `whenLoaded()` when you need custom loaded/not-loaded behavior
8. **Includes pattern**: Use `reference()` when you only need an identifier object
9. **Filter sync**: Every OpenAPI filter attribute (`IdFilter`, etc.) must have a matching `QueryFilter` method — both layers must exist
10. **Use FilterParameter**: Always wrap typed filters in `FilterParameter([...])`, never use the legacy `QueryFilter` (OpenApi namespace) directly
11. **Column mapping**: Second parameter in `QueryFilter` methods maps API camelCase name to internal DB column name
12. **Success codes**: Use `successStatus: '201'` for POST endpoints, DELETE returns `204`
13. **Actions are PATCH**: Custom actions use `ActionEndpoint` at `/resources/{id}/actions/{action}`
14. **Pagination type sync**: `PaginationType` values in endpoint attribute must match those passed to `apiPaginate()`
15. **Generate after changes**: Run `php artisan openapi:generate` after modifying any OpenAPI attributes
