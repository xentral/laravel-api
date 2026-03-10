## xentral/laravel-api

A Laravel package (namespace `Xentral\LaravelApi`) providing strongly-typed PHP attributes for defining OpenAPI specifications alongside your code, with an extended QueryBuilder for filtering, sorting, and pagination.

## Mandatory Architecture Rules

- Always extend `Xentral\LaravelApi\Http\ApiResource`, never `JsonResource` directly
- Always use `Xentral\LaravelApi\Query\QueryBuilder::for()`, never plain Eloquent or `Spatie\QueryBuilder\QueryBuilder` directly
- Always set `additionalProperties: false` on every `OA\Schema`
- Always add `/** @var ModelName */ public $resource;` type annotation on resources
- Every `OA\Schema` must specify `schema`, `required`, `properties`, `type`
- OpenAPI `required` fields must match Laravel validation `required` rules
- Format datetime with `->toAtomString()`, date with `->toDateString()`
- Paginate via `->apiPaginate()`, never Laravel's `->paginate()` or `->simplePaginate()`
- POST endpoints: `successStatus: '201'`, DELETE endpoints return `204`
- Actions use `ActionEndpoint` (PATCH) at `/resources/{id}/actions/{action}`
- Run `php artisan openapi:generate` after any attribute changes

## Two-Layer Filter System

Filtering requires **two parallel declarations** that must stay in sync:

1. **OpenAPI spec layer**: `FilterParameter` with typed filter attributes (`IdFilter`, `StringFilter`, `NumberFilter`, `DateFilter`, `DateTimeFilter`, `EnumFilter`, `BooleanFilter`)
2. **QueryBuilder layer**: `QueryFilter::identifier()`, `::string()`, `::number()`, `::date()`, `::datetime()`, `::boolean()`, `::booleanInteger()`

Both layers must declare the same filter names. API field names use camelCase; map to internal column name via the second parameter (e.g. `QueryFilter::date('documentDate', 'datum')`).

## Resource Helpers Quick Reference

- `includeWhenLoaded(relation, ResourceClass)` — returns full resource when loaded, falls back to `reference()` (id-only)
- `reference(relation)` — returns `['id' => foreignKey]` or `null`
- `wantsToInclude(name)` — checks if client requested this include via `?include=`
- `nullWhenEmpty(data, key)` — returns `null` instead of empty value
- `deprecatedSince(DateTimeInterface)` — adds `Sunset` HTTP header to response

## Endpoint Attributes Overview

- `ListEndpoint` — paginated GET collection with filters, sorts, includes, pagination type
- `GetEndpoint` — single resource GET, supports `additionalMediaTypes` (e.g. `PdfMediaType`)
- `PostEndpoint` — create resource, accepts `request` and `successStatus`
- `PatchEndpoint` — update resource
- `PutEndpoint` — full replacement update
- `DeleteEndpoint` — delete resource, accepts `validates` for conditional deletion messages
- `ActionEndpoint` — extends `PatchEndpoint` for custom state-changing actions

All endpoint attributes support: `isInternal`, `deprecated` (DateTimeInterface), `featureFlag`, `scopes`, `problems`, `tags`, `security`.

## Anti-Patterns

- Never define filters only on one side (OpenAPI or QueryBuilder) — both must exist and match
- Never use `DummyInclude` for real database relationships — only for virtual/computed includes (e.g. `lineItems.customFields`)
- Never use `PaginationType` values in the endpoint attribute without passing the same types to `apiPaginate()`
- Never use the legacy `QueryFilter` (OpenApi namespace) or `FilterProperty` directly — use `FilterParameter` with typed filters (`IdFilter`, `StringFilter`, etc.)

## Skill Reference

Use the `api-development` skill for full code examples, reference tables, and how-to guides.
