## xentral/laravel-api

A Laravel package providing strongly-typed PHP attributes for defining OpenAPI specifications alongside your code. Uses `spatie/laravel-query-builder` for filtering and generates comprehensive API documentation.

## Key Concepts

- **Co-located Specifications**: Define OpenAPI specs using PHP attributes directly on controllers, resources, and request classes.
- **Automatic Documentation**: Generate OpenAPI schema files from code with `php artisan openapi:generate`.
- **QueryBuilder**: Extended `Xentral\LaravelApi\Query\QueryBuilder` with built-in filtering and pagination support.
- **Multiple Schemas**: Support for multiple API versions/schemas within one application.

## File Structure & Conventions

### Resources (JSON Responses)
- Extend `Xentral\LaravelApi\Http\ApiResource` (not Laravel's JsonResource directly)
- Add `#[OA\Schema]` attribute to define OpenAPI schema
- Always specify `schema`, `required`, `properties`, `type`
- Use `$this->resource` property (typed as your model) for IDE support
- Use `$this->whenLoaded('relation', fn() => ...)` for optional includes
- Format dates with `->toDateString()`
- Format datetime with `->toAtomString()`


```php
use Xentral\LaravelApi\Http\ApiResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SalesOrder',
    required: ['id', 'status', 'created_at'],
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
        new OA\Property(property: 'created_at', type: 'datetime'),
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
            'customer' => $this->whenLoaded(
                'customer',
                fn() => new CustomerResource($this->resource->customer),
                ['id' => $this->resource->customer_id]
            ),
            'created_at' => $this->resource->created_at->toAtomString(),
        ];
    }
}
```

### Request Bodies (Validation)
- Use FormRequest or spatie/laravel-data Data objects
- Add `#[OA\Schema]` attribute with full specification
- Schema required fields and Laravel validation rules must match
- Always set `additionalProperties: false` to enforce strict validation

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

### List Endpoints
Use `#[ListEndpoint]` for paginated collection endpoints. Always use `QueryBuilder::for()` (not Eloquent's query builder).

```php
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Filters\{IdFilter, StringFilter, DateFilter};
use Xentral\LaravelApi\OpenApi\{QuerySort, PaginationType};
use Xentral\LaravelApi\Query\{QueryBuilder, Filters\QueryFilter};

#[ListEndpoint(
    path: '/api/v1/sales-orders',
    resource: SalesOrderResource::class,
    description: 'Paginated list of sales orders',
    includes: ['customer', 'positions'],
    parameters: [
        new IdFilter(),
        new StringFilter(name: 'documentNumber'),
        new DateFilter(name: 'documentDate'),
        new QuerySort(['created_at', 'updated_at']),
    ],
    paginationType: PaginationType::SIMPLE,
    tags: ['SalesOrder'],
)]
public function index(): ResourceCollection
{
    $orders = QueryBuilder::for(SalesOrder::class)
        ->defaultSort('-created_at')
        ->allowedFilters([
            QueryFilter::identifier(),
            QueryFilter::string('documentNumber'),
            QueryFilter::date('documentDate', 'datum'), // second param is internal column name
        ])
        ->allowedSorts(['created_at', 'updated_at'])
        ->allowedIncludes(['customer', 'positions'])
        ->apiPaginate(PaginationType::SIMPLE);

    return SalesOrderResource::collection($orders);
}
```

**Pagination Types**:
- `PaginationType::SIMPLE` - Basic prev/next (most efficient)
- `PaginationType::TABLE` - Full pagination with page numbers and totals
- `PaginationType::CURSOR` - Cursor-based for large datasets

Can specify multiple types: `paginationType: [PaginationType::SIMPLE, PaginationType::TABLE]`. Client controls via `X-Pagination` header.

### View Endpoints
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

### Create Endpoints
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

### Update Endpoints
Use `#[PatchEndpoint]` or `#[PutEndpoint]`.

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

### Delete Endpoints
Use `#[DeleteEndpoint]` with `validates` parameter for custom error messages.

```php
use Xentral\LaravelApi\OpenApi\Endpoints\DeleteEndpoint;
use Illuminate\Validation\ValidationException;

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

## QueryBuilder Filters

Use `QueryFilter` factory methods for consistent filtering:

```php
// String filters - supports: eq, neq, in, notIn, contains, notContains, startsWith, endsWith, isNull, isNotNull
QueryFilter::string('name')
QueryFilter::string('status', 'internal_status_column') // with custom column

// Number filters - supports: eq, neq, lt, lte, gt, gte, isNull, isNotNull
QueryFilter::number('quantity')

// Date filters - supports: eq, neq, lt, lte, gt, gte, isNull, isNotNull
QueryFilter::date('createdAt', 'created_at')

// Boolean filters - supports: eq, neq
QueryFilter::boolean('isActive', 'active')

// ID filters - supports: eq, neq, in, notIn, isNull, isNotNull
QueryFilter::identifier()
QueryFilter::identifier('customerId', 'customer_id')
```

**Filter URL Format**:
```
/api/v1/sales-orders?filter[0][key]=documentNumber&filter[0][op]=eq&filter[0][value]=12345
/api/v1/sales-orders?filter[0][key]=documentDate&filter[0][op]=lessThan&filter[0][value]=2025-05-05
/api/v1/sales-orders?filter[0][key]=status&filter[0][op]=in&filter[0][value][]=pending&filter[0][value][]=approved
```

## Custom Filter Collections

Reuse filters across endpoints by implementing `FilterSpecCollection`:

```php
use Xentral\LaravelApi\OpenApi\Filters\{FilterProperty, FilterSpecCollection};
use Xentral\LaravelApi\Query\Filters\FilterType;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SalesOrderFilters implements FilterSpecCollection
{
    public function getFilterSpecification(): array
    {
        return [
            new FilterProperty(
                name: 'documentNumber',
                description: 'Filter by document number',
                type: 'string',
                filterType: FilterType::PARTIAL
            ),
            new FilterProperty(
                name: 'status',
                description: 'Filter by status',
                type: 'string',
                filterType: FilterType::EXACT
            ),
        ];
    }
}
```

Then use in controller: `parameters: [new SalesOrderFilters(), new QuerySort(['created_at'])]`

## Generating OpenAPI Schema

```bash
php artisan openapi:generate          # Generate default schema
php artisan openapi:generate v1       # Generate specific schema
```

Schema files are defined in `config/openapi.php`. Each schema can scan different folders and output to different files.

## Configuration

Publish config: `php artisan vendor:publish --provider="Xentral\LaravelApi\ApiServiceProvider"`

**Key Config Options**:
- `schemas.default.folders` - Directories to scan for attributes
- `schemas.default.output` - Output file path (e.g., `base_path('openapi.yml')`)
- `schemas.default.pagination_response.casing` - `'snake'` or `'camel'` for response field naming
- `docs.enabled` - Enable web documentation interface
- `docs.prefix` - URL prefix (default: `/api-docs`)
- `docs.client` - Documentation client: `'swagger'` or `'scalar'`

## Important Rules

1. **Always use QueryBuilder**: Use `Xentral\LaravelApi\Query\QueryBuilder::for()`, never plain Eloquent
2. **Match validation to schema**: OpenAPI schema `required` fields must match Laravel validation rules
3. **Set additionalProperties false**: Always add `additionalProperties: false` to schemas except explicitly needing additional properties
4. **Use typed resources**: Add `/** @var ModelName */ public $resource;` for IDE support
5. **Date formatting**: Always use `->toAtomString()` for datetime fields
6. **Includes pattern**: Use `$this->whenLoaded()` for optional related resources
7. **Includes pattern**: Use `$this->includeWhenLoaded()` if you need the id present if not loaded
8. **Includes pattern**: Use `$this->reference()` if you need add only an identifier
9. **Filter column mapping**: Second parameter in QueryFilter methods maps to internal column name
10. **Success codes**: Use `successStatus: '201'` for POST endpoints, `204` for DELETE
11. **Actions are PATCH**: Custom actions use PATCH method at `/resources/{id}/actions/{action}`
12. **Generate after changes**: Run `php artisan openapi:generate` after modifying any attributes
