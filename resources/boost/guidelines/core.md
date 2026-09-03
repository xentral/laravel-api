## xentral/laravel-api

A Laravel package (namespace `Xentral\LaravelApi`) providing strongly-typed PHP attributes for defining OpenAPI specifications alongside your code, with an extended QueryBuilder for filtering, sorting, and pagination.

## Mandatory Architecture Rules

- Always extend `Xentral\LaravelApi\Http\ApiResource`, never `JsonResource` directly
- Always use `Xentral\LaravelApi\Query\QueryBuilder::for()`, never plain Eloquent or `Spatie\QueryBuilder\QueryBuilder` directly
- Always set `additionalProperties: false` on response `OA\Schema` (resources), never on request schemas — requests ignore undeclared properties
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
2. **QueryBuilder layer**: `QueryFilter::identifier()`, `::identifierCallback()`, `::string()`, `::number()`, `::date()`, `::datetime()`, `::boolean()`, `::booleanInteger()`

Both layers must declare the same filter names. API field names use camelCase; map to internal column name via the second parameter (e.g. `QueryFilter::date('documentDate', 'datum')`).

## Boolean Filter Groups

- The `filter` parameter also accepts one group object `{"op": "and"|"or", "conditions": [{key, op, value}, ...]}`, as a JSON string or in deepObject form. An `and` group means what the flat filter list already means and works on every endpoint.
- An `or` group requires the endpoint to opt in on **both** layers: `QueryBuilder::for(...)->allowOrFilterGroups(except: [...])` **before** `allowedFilters()`, and `new FilterParameter([...], supportsOrGroups: true)` on the spec side. Neither layer alone is valid.
- A condition may itself be a group, so `(A and B) or (C and D)` is one request. Groups nest to at most `QueryBuilderRequest::MAX_GROUP_DEPTH` (5) levels, the outermost group counting as level one; deeper is a validation error.
- **Nesting rides on the same opt-in**, whatever the operators are: a group holding a sub-group on an endpoint that never called `allowOrFilterGroups()` is rejected, even when every operator is `and`.
- Opted-in endpoints also pass `groupSchemaName:` to `FilterParameter` (e.g. `groupSchemaName: 'ProductFilter'`), which emits `{name}Condition` / `{name}Group` components and points the parameter at the recursive `$ref`. Without it the spec still declares the old single-level group.
- Put every filter that mutates query-wide state (one that lifts a global scope) or that pairs several keys into `except` — such a filter is not a self-contained branch. Those filters stay usable as **direct conditions of a top-level `and` group** (and in the flat list); anywhere below that they are rejected, because only the top level is applied on the outer builder.
- Filter defaults for keys the request does not name, and the `search` parameter, stay conjunctive: they narrow the whole result set outside the group. A key named anywhere in the tree, at any depth, counts as named.

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
