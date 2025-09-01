# Laravel OpenApi

[![Latest Version on Packagist](https://img.shields.io/packagist/v/xentral/laravel-api.svg?style=flat-square)](https://packagist.org/packages/xentral/laravel-api)
[![Total Downloads](https://img.shields.io/packagist/dt/xentral/laravel-api.svg?style=flat-square)](https://packagist.org/packages/xentral/laravel-api)
![GitHub Actions](https://github.com/xentral/laravel-api/actions/workflows/main.yml/badge.svg)

This package provides comprehensive tools for API specification, documentation, and implementation, enabling engineers
to build a
unified API experience.

Instead of managing YAML or JSON files, you can use strongly-typed PHP attributes to define your
API endpoints and schemas directly alongside the responsible code. The provided attributes work seamlessly with
the extended `QueryBuilder` from the `spatie/laravel-query-builder` package to provide a straightforward way of
implementing your specified API endpoints.

There were six main goals in mind when creating this package:

* Reduce the required boilerplate as much as possible
* Co-locate endpoint specifications to controllers, validation specifications to request classes, and schema
  specifications to resource classes
* Generate documentation from specification (not implementation), so the schema can be used for test validation
* Provide structured guard rails around API implementation, making it easy to onboard new developers
* Generate OpenAPI schema files that can be used for PHPUnit testing, documentation, and client generation
* Provide a web interface to easily view and interact with the OpenAPI documentation

## Key Features

* **Flexible Pagination**: Support for simple, table, and cursor pagination with dynamic type switching via headers
* **Type-Safe Attributes**: Strongly-typed PHP attributes for defining endpoints, schemas, and validation
* **Advanced Filtering**: Powerful query filtering with intuitive URL-based syntax
* **Multiple Schema Support**: Generate multiple OpenAPI specifications from different parts of your application
* **Configurable Response Formats**: Choose between snake_case and camelCase for response field naming
* **Automatic Documentation**: Generate comprehensive OpenAPI documentation directly from your code
* **Built-in Validation**: Automatic request/response validation against your OpenAPI schemas
* **Web Interface**: Interactive documentation browser for testing and exploring your API

## How does it work?

`xentral/laravel-api` is built upon the excellent `zircote/swagger-php` and
`spatie/laravel-query-builder` packages, adding powerful features for Laravel applications.

The package enables you to manage multiple OpenAPI schema files within a single project. Default configuration is
handled via
the `openapi.php` config file.

The package provides opinionated and straightforward PHP 8 attributes to define OpenAPI specifications directly in your
controller methods and request/resource classes. It includes a set of predefined attributes for common HTTP
methods (GET, POST, PUT, PATCH, DELETE) that automatically:

- Generate endpoint documentation with proper path parameters
- Document request bodies and validation requirements
- Define response schemas and status codes
- Handle authentication and authorization responses

These attributes extract the necessary information from your code structure, reducing duplication and keeping your API
documentation in sync with your implementation.

## Installation

Install the package via Composer:

```bash
composer require xentral/laravel-api
```

The package will automatically register its service provider and be ready to use.

## Usage

The following sections demonstrate how to define your API endpoints and schemas using PHP attributes.

### Resource Definition

API resources are defined using the `#[OA\Schema]` attribute. This allows you to specify the properties of your
resource, including their types and validation requirements. The example below demonstrates how to define a simple
`SalesOrder` resource.

```php
<?php

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;
#[OA\Schema(
    schema: 'SalesOrder',
    required: ['id', 'status', 'customer', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', ref: SalesOrderStatus::class),
        new OA\Property(property: 'customer', anyOf: [
            new OA\Schema(ref: CustomerResource::class),
            new OA\Schema(properties: [new OA\Property(property: 'id', type: 'integer')], type: 'object'),
        ]
        ),
        new OA\Property(property: 'positions', type: 'array', items: new OA\Items(ref: SalesOrderPositionResource::class), nullable: true),
        new OA\Property(property: 'created_at', type: 'datetime'),
        new OA\Property(property: 'updated_at', type: 'datetime'),
    ],
    type: 'object',
    additionalProperties: false,
)]
class SalesOrderResource extends JsonResource
{
    /** @var SalesOrder */
    public $resource;

    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->resource->customer), ['id' => $this->resource->customer_id]),
            'positions' => $this->whenLoaded('positions', fn () => SalesOrderPositionResource::collection($this->resource->positions)),
            'created_at' => $this->resource->created_at->format(DATE_ATOM),
            'updated_at' => $this->resource->updated_at->format(DATE_ATOM),
        ];
    }
}
```

### List Endpoints

You can define a list endpoint using the `#[ListEndpoint]` attribute. This allows you to specify the path, resource
class, description, and any additional parameters such as filters, sorts, and includes. The example below shows how to
define a list endpoint for sales orders.

```php
<?php

use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\OpenApi\Filters\DateFilter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\OpenApi\QuerySort;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\QueryBuilder;

// Simple endpoint with single pagination type
#[ListEndpoint(
        path: '/api/v1/sales-orders',
        resource: SalesOrderResource::class,
        description: 'Paginated list of sales orders',
        includes: ['customer', 'positions'],
        parameters: [
            new IdFilter(),
            new StringFilter(name: 'documentNumber'),
            new DateFilter(name: 'documentDate'),
            /// ...
            new QuerySort(['created_at', 'updated_at']),
        ],
        paginationType: PaginationType::SIMPLE,
        tags: ['SalesOrder'],
    )]
    public function index(): ResourceCollection
    {
        $salesOrders = QueryBuilder::for(SalesOrder::class)
            ->withCount('positions')
            ->defaultSort('-created_at')
            ->allowedFilters([
                QueryFilter::identifier(),
                QueryFilter::string('documentNumber'),
                QueryFilter::date('documentDate', 'datum'),
                new AllowedFilter('positions.count', new RelationCountFilter(),'positions'),
                // ...
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('updated_at'),
            ])
            ->allowedIncludes([
                'customer',
                'positions',
            ])
            ->apiPaginate(PaginationType::SIMPLE);

        return SalesOrderResource::collection($salesOrders);
    }

// Advanced endpoint with multiple pagination types
#[ListEndpoint(
        path: '/api/v1/sales-orders-advanced',
        resource: SalesOrderResource::class,
        description: 'Advanced paginated list with multiple pagination options',
        includes: ['customer', 'positions'],
        parameters: [
            new IdFilter(),
            new StringFilter(name: 'documentNumber'),
            new DateFilter(name: 'documentDate'),
            new QuerySort(['created_at', 'updated_at']),
        ],
        paginationType: [PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR],
        tags: ['SalesOrder'],
    )]
    public function indexAdvanced(): ResourceCollection
    {
        $salesOrders = QueryBuilder::for(SalesOrder::class)
            ->withCount('positions')
            ->defaultSort('-created_at')
            ->allowedFilters([
                QueryFilter::identifier(),
                QueryFilter::string('documentNumber'),
                QueryFilter::date('documentDate', 'datum'),
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('updated_at'),
            ])
            ->allowedIncludes([
                'customer',
                'positions',
            ])
            ->apiPaginate(PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR);

        return SalesOrderResource::collection($salesOrders);
    }
```

#### Filtering

This package leverages the `spatie/laravel-query-builder` package to provide an intuitive filter implementation.
However, the filters are adapted to follow our own conventions. Each filter in the URL always
contains `key`, `op`, and `value` parameters. Here are some examples:

```bash
/api/v1/sales-orders?filter[0][key]=documentNumber&filter[0][op]=eq&filter[0][value]=12345
/api/v1/sales-orders?filter[0][key]=documentNumber&filter[0][op]=in&filter[0][value][]=12345&filter[0][value][]=54321
/api/v1/sales-orders?filter[0][key]=documentDate&filter[0][op]=lessThan&filter[0][value]=2025-05-05
/api/v1/sales-orders?filter[0][key]=customer.name&filter[0][op]=contains&filter[0][value]=John
```

#### Pagination

This package provides flexible pagination options that can be configured per endpoint. You can choose from three different pagination types, each optimized for different use cases:

##### Pagination Types

**Simple Pagination** (`PaginationType::SIMPLE`)
- Basic prev/next navigation without total counts
- Most efficient for large datasets
- Provides: `current_page`, `per_page`, `from`, `to`, `path`, and navigation links

**Table Pagination** (`PaginationType::TABLE`)  
- Full pagination with page numbers and totals
- Best for user interfaces with page selectors
- Provides: All simple pagination fields plus `last_page`, `total`, and detailed link information

**Cursor Pagination** (`PaginationType::CURSOR`)
- Efficient pagination for real-time data and large datasets
- Provides: `per_page`, `path`, `next_cursor`, `prev_cursor`, and cursor-based navigation links
- Uses database cursors for consistent results even when data changes

##### Dynamic Pagination Control

For endpoints that support multiple pagination types, clients can control the pagination format using the `x-pagination` header:

```bash
# Use simple pagination (default)
GET /api/v1/sales-orders-advanced
X-Pagination: simple

# Use table pagination with totals and page numbers
GET /api/v1/sales-orders-advanced  
X-Pagination: table

# Use cursor-based pagination
GET /api/v1/sales-orders-advanced
X-Pagination: cursor
```

##### Pagination Parameters

Different pagination types use different query parameters:

**Simple & Table Pagination:**
```bash
/api/v1/sales-orders?page=2&per_page=50
```

**Cursor Pagination:**
```bash
/api/v1/sales-orders?cursor=eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0&per_page=50
```

##### Configurable Response Format

You can configure whether pagination fields use `snake_case` or `camelCase` in your configuration:

```php
// Snake case (default): current_page, per_page, last_page
'pagination_response' => [
    'casing' => 'snake',
],

// Camel case: currentPage, perPage, lastPage  
'pagination_response' => [
    'casing' => 'camel',
],
```

### View Endpoints

View endpoints are defined using the `#[GetEndpoint]` attribute. This allows you to specify the path, resource
class, description, and additional parameters such as includes. The example below demonstrates how to define a view
endpoint for retrieving a single sales order.

```php
<?php

use Xentral\LaravelApi\Query\QueryBuilder;
#[GetEndpoint(
        path: '/api/v1/sales-orders/{id}',
        resource: SalesOrderResource::class,
        description: 'View a single sales order',
        tags: ['SalesOrder'],
        includes: ['customer', 'positions'],
    )]
    public function view(int $id): SalesOrderResource
    {
        $salesOrder = QueryBuilder::for(SalesOrder::class)
            ->allowedIncludes([
                'customer',
                'positions',
            ])
            ->findOrFail($id);

        return new SalesOrderResource($salesOrder);
    }
```

### Defining Request Bodies

Request bodies are defined similarly to resources, using the `#[OA\Schema]` attribute. This is applied to
Laravel's Form Request classes (or other data objects like spatie/laravel-data). This allows you to specify the
properties of your request
body, including their types and validation requirements. The example below demonstrates how to define a request body for
creating a sales order.

```php
<?php

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSalesOrderRequest',
    required: ['project', 'customer', 'positions'],
    properties: [
        new OA\Property(property: 'project', type: 'object', required: ['id'], properties: [new OA\Property(property: 'id', type: 'integer')]),
        new OA\Property(property: 'customer', type: 'object', required: ['id'], properties: [new OA\Property(property: 'id', type: 'integer')]),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'positions', type: 'array', items: new OA\Items(
            required: ['sku', 'quantity', 'price'],
            properties: [
                new OA\Property(property: 'sku', type: 'string'),
                new OA\Property(property: 'quantity', type: 'integer'),
                new OA\Property(property: 'price', ref: '#/components/schemas/Money'),
            ])),
    ],
    type: 'object',
    additionalProperties: false,
)]
class CreateSalesOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'project.id' => ['required', 'integer', 'exists:projects,id'],
            'customer.id' => ['required', 'integer', 'exists:business_partners,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'positions' => ['required', 'array'],
            'positions.*.sku' => ['required', 'string', 'exists:products,sku'],
            'positions.*.quantity' => ['required', 'integer'],
            'positions.*.price' => ['required'],
            'positions.*.price.amount' => ['required', 'decimal:0,2'],
            'positions.*.price.currency' => ['required', 'in:EUR,USD'],
        ];
    }
}
```

### Create Endpoints

Create endpoints are defined using the `#[PostEndpoint]` attribute. This allows you to specify the path, resource
class, description, and additional parameters such as request body and response. The `successStatus` parameter allows
you to specify a different success status code (default is 200). The example below demonstrates how to define a create
endpoint for a sales order.

```php
<?php

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
        $salesOrder = SalesOrder::create([
            // ...
        ]);

        return new SalesOrderResource($salesOrder);
    }


```

### Update Endpoints

Update endpoints are defined using either the `#[PutEndpoint]` or `#[PatchEndpoint]` attribute. These work
identically to the `#[PostEndpoint]` attribute, allowing you to specify the path, resource class, description, and
additional parameters such as request body and response. The example below demonstrates how to define an update endpoint
for a sales order.

```php
<?php

#[PatchEndpoint(
        path: '/api/v1/sales-orders/{id}',
        request: UpdateSalesOrderRequest::class,
        resource: SalesOrderResource::class,
        description: 'Update an existing sales order',
        tags: ['SalesOrder'],
    )]
    public function update(UpdateSalesOrderRequest $request, int $id): SalesOrderResource
    {
        $salesOrder = SalesOrder::with('positions')->findOrFail($id);

        // Handle update logic here, e.g. updating positions, customer, etc.

        return new SalesOrderResource($salesOrder);
    }
```

### Delete Endpoints

Delete endpoints are defined using the `#[DeleteEndpoint]` attribute. This allows you to specify the path,
description, and additional parameters such as custom validation messages. The example below demonstrates how to define
a delete
endpoint for a sales order with custom validation to ensure that only pending sales orders can be deleted.
When a sales order is not in a pending state, a validation exception is thrown with a descriptive message.

```php
<?php

use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

    #[DeleteEndpoint(
        path: '/api/v1/sales-orders/{id}',
        description: 'Delete a sales order',
        tags: ['SalesOrder'],
        validates: ['status' => 'Only pending sales orders can be deleted.'],
    )]
    public function delete(int $id): Response
    {
        $salesOrder = SalesOrder::findOrFail($id);

        if ($salesOrder->status !== SalesOrderStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending sales orders can be deleted. Current status: '.$salesOrder->status->value,
            ]);
        }

        $salesOrder->positions()->delete();
        $salesOrder->delete();

        return response()->noContent();
    }
```

## Generating OpenAPI Schema

Once you have defined your endpoints and schemas, generate the OpenAPI specification file:

```bash
php artisan openapi:generate
```

### Configuration

After installation, you can publish the configuration file using:

```bash
php artisan vendor:publish --provider="Xentral\LaravelApi\ApiServiceProvider"
```

This will create a `config/openapi.php` file with the following options:

```php
return [
    'docs' => [
        'enabled' => env('APP_ENV') !== 'production',
        'prefix' => 'api-docs',
        'middleware' => ['web', 'auth'],
    ],
    'schemas' => [
        'default' => [
            'oas_version' => '3.1.0',
            'ruleset' => null,
            'folders' => [base_path('app')],
            'output' => base_path('openapi.yml'),
            'deprecation_filter' => [
                'enabled' => true,
                'months_before_removal' => 6,
            ],
            'feature_flags' => [
                'description_prefix' => "This endpoint is only available if the feature flag `{flag}` is enabled.\n\n",
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
            'pagination_response' => [
                'casing' => 'snake', // 'snake' or 'camel'
            ],
            'validation_commands' => [],
            'validation_status_code' => 422,
            'name' => 'My API',
            'version' => '1.0.0',
            'description' => 'Developer API',
            'contact' => [
                'name' => 'API Support',
                'url' => env('APP_URL', 'https://example.com'),
                'email' => env('MAIL_FROM_ADDRESS', 'api@example.com'),
            ],
            'servers' => [
                [
                    'url' => env('APP_URL', 'https://example.com'),
                    'description' => 'Your API environment',
                ],
            ],
        ],
    ],
];
```

#### Multiple Schemas

You can define multiple schemas in the configuration file. Each schema can have its own settings, including which
folders to scan, output file, and other OpenAPI information.

```php
'schemas' => [
    'v1' => [
        'folders' => [base_path('app/Http/Controllers/Api/V1')],
        'output' => base_path('openapi-v1.yml'),
        // other settings...
    ],
    'v2' => [
        'folders' => [base_path('app/Http/Controllers/Api/V2')],
        'output' => base_path('openapi-v2.yml'),
        // other settings...
    ],
],
```

To generate a specific schema, you can pass the schema name to the `openapi:generate` command:

```bash
php artisan openapi:generate v1
```

### Web Interface

The package includes a built-in web interface for viewing and interacting with your OpenAPI documentation.
By default, it's available at `/api-docs` and is protected by the `web` and `auth` middleware.

You can configure the web interface in the `docs` section of the configuration file:

```php
'docs' => [
    'enabled' => env('APP_ENV') !== 'production', // Enable or disable the web interface
    'prefix' => 'api-docs', // URL prefix for the web interface
    'middleware' => ['web', 'auth'], // Middleware applied to the web interface routes
],
```

### Reusing Filters

Reusing filters across multiple endpoints improves consistency and reduces duplication. This is achieved by creating a
custom Attribute class
that implements the `FilterSpecCollection` interface. Here's an example:

```php
<?php

namespace App\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterType;use Xentral\LaravelApi\OpenApi\Filters\FilterProperty;use Xentral\LaravelApi\OpenApi\Filters\FilterSpecCollection;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class UserFilters implements FilterSpecCollection
{
    public function getFilterSpecification(): array
    {
        return [
            new FilterProperty(
                name: 'name',
                description: 'Filter users by name',
                type: 'string',
                filterType: FilterType::PARTIAL
            ),
            new FilterProperty(
                name: 'email',
                description: 'Filter users by email',
                type: 'string',
                filterType: FilterType::EXACT
            ),
            new FilterProperty(
                name: 'created_at',
                description: 'Filter users by creation date',
                type: 'string',
                filterType: FilterType::OPERATOR,
                operators: ['eq', 'gt', 'lt', 'gte', 'lte']
            ),
        ];
    }
}
```

Once defined, you can use this custom filter collection in your controller methods:

```php
#[ListEndpoint(
    path: '/api/v1/users',
    resource: UserResource::class,
    description: 'Paginated list of users',
    parameters: [
        new UserFilters(),
        new QuerySort(['created_at', 'updated_at']),
    ],
    paginationType: PaginationType::SIMPLE,
    tags: ['User'],
)]
public function index(): ResourceCollection
{
    $users = QueryBuilder::for(User::class)
        ->defaultSort('-created_at')
        ->allowedFilters([
            QueryFilter::string('name'),
            QueryFilter::string('email'),
            QueryFilter::date('created_at'),
        ])
        ->allowedSorts([
            AllowedSort::field('created_at'),
            AllowedSort::field('updated_at'),
        ])
        ->apiPaginate(PaginationType::SIMPLE);

    return UserResource::collection($users);
}
```

## Testing

Use the package [xentral/laravel-testing](https://github.com/xentral/laravel-testing) to validate your API requests and
responses against the OpenAPI schemas defined in your tests.

## Advanced Configuration

### Schema Configuration Options

- **`oas_version`**: OpenAPI specification version (default: `3.1.0`)
- **`ruleset`**: Custom validation ruleset (optional)
- **`folders`**: Array of folders to scan for attributes
- **`output`**: Path where the generated OpenAPI file will be saved
- **`deprecation_filter`**: Configuration for handling deprecated endpoints
    - `enabled`: Whether to apply deprecation filtering
    - `months_before_removal`: Number of months before deprecated endpoints are removed
- **`feature_flags`**: Configuration for feature flag documentation
    - `description_prefix`: Template for feature flag descriptions
- **`validation_response`**: Configuration for validation error responses
    - `status_code`: HTTP status code for validation errors (default: `422`)
    - `content_type`: Response content type (default: `application/json`)
    - `max_errors`: Maximum number of errors to include in response
    - `content`: Template for validation error response structure
- **`pagination_response`**: Configuration for pagination response formatting
    - `casing`: Field name casing format - `snake` (default) or `camel`
- **`validation_commands`**: Array of commands to run for validation after generation
- **`validation_status_code`**: HTTP status code for validation errors (default: `422`)

### Web Interface Configuration

- **`docs.enabled`**: Enable/disable the web documentation interface
- **`docs.prefix`**: URL prefix for the documentation (default: `api-docs`)
- **`docs.middlewares`**: Additional middleware to apply to documentation routes

## Best Practices

### Organizing Your API

1. **Use consistent naming**: Keep your resource names consistent across endpoints, schemas, and tags
2. **Group related endpoints**: Use tags to group related endpoints together
3. **Document all parameters**: Always provide descriptions for filters, includes, and other parameters
4. **Use feature flags**: Mark experimental endpoints with feature flags for better API governance

### Schema Design

1. **Keep schemas focused**: Each schema should represent a single concept
2. **Use references**: Leverage `$ref` to reuse common schemas and avoid duplication
3. **Mark required fields**: Always specify which fields are required in your schemas
4. **Use appropriate types**: Use the most specific OpenAPI type for each field

### Pagination Guidelines

1. **Choose the right pagination type**: 
   - Use **Simple** for basic list views and mobile APIs where performance is critical
   - Use **Table** for admin interfaces and dashboards where users need page numbers and totals
   - Use **Cursor** for real-time feeds, activity streams, and very large datasets
2. **Support multiple types**: For flexible APIs, support multiple pagination types and let clients choose
3. **Consider your audience**: Internal tools may prefer table pagination, while public APIs often benefit from simple or cursor pagination
4. **Use appropriate defaults**: Set sensible `defaultPageSize` and `maxPageSize` values for your use case

### Performance Considerations

1. **Limit includes**: Be selective about which relationships can be included to avoid N+1 queries
2. **Set reasonable pagination limits**: Use `maxPageSize` to prevent excessive data loading
3. **Use caching**: Consider caching generated OpenAPI files in production
4. **Choose efficient pagination**: Cursor pagination performs better on large datasets than offset-based pagination

## Troubleshooting

### Common Issues

#### Schema Generation Fails

- Ensure all referenced classes exist and are autoloadable
- Verify your attribute syntax is correct
- Check that folder paths in your configuration are valid
- Confirm that the output directory is writable

#### Validation Errors

- Ensure your Laravel validation rules match your OpenAPI schema definitions
- Verify that required fields are properly marked in both validation rules and schemas
- Check that data types are consistent between validation and schema
- Review enum values for exact matches

#### Missing Endpoints in Documentation

- Confirm your controller methods have the appropriate endpoint attributes
- Verify that controller files are in the configured scan folders
- Check your attribute syntax for typos or missing parameters
- Ensure the class is properly autoloaded

#### Performance Issues

- Implement caching for generated schemas in production
- Limit the number of folders being scanned to only necessary directories
- Use specific folder paths instead of scanning the entire application directory
- Consider excluding vendor directories from scans

### Getting Help

If you encounter issues:

1. Review the generated OpenAPI file for syntax errors or missing definitions
2. Enable Laravel's debug mode to see detailed error messages
3. Examine the package's test suite for example usage patterns
4. Search the GitHub issue tracker for similar problems and solutions
5. Check that your PHP and Laravel versions meet the package requirements

## Contributing

### Roadmap

Future development ideas include:

- Support for additional OpenAPI documentation tools beyond Swagger UI
- Enhanced OpenAPI 3.1 features like callbacks, webhooks, and links
- Improved documentation generation with additional examples and use cases
- Client library generation for multiple programming languages


Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email api@xentral.com instead of using the issue tracker.

## Credits

- [Manuel Christlieb](https://github.com/bambamboole) - Creator and maintainer
- All contributors who have helped improve this package

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
