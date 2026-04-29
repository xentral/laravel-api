# Disable Pagination on `ListEndpoint`

Date: 2026-04-29
Status: Accepted

## Summary

Allow consumers to declare a list endpoint with no pagination by passing `paginationType: null` to `Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint`. The generated OpenApi spec for such an endpoint omits all pagination-related query parameters (`per_page`, `page`, `cursor`), the `x-pagination` header, and the `meta` / `links` properties from the 200 response. Runtime behaviour stays the developer's responsibility — they simply do not call `->apiPaginate()` and let Laravel's `ResourceCollection` produce the standard `{data: [...]}` envelope.

## Motivation

Today, every endpoint annotated with `#[ListEndpoint(...)]` ends up paginated in the OpenApi spec because `paginationType` defaults to `PaginationType::SIMPLE` and there is no way to opt out. Real APIs sometimes need flat list endpoints — typeahead lookups, small reference data, "list all of X" exports — where pagination is meaningless. Consumers currently have to either accept the spurious pagination parameters in their published spec or hand-write a raw `Get` attribute and reimplement the response shape. A first-class opt-out closes that gap.

## Public API

### `ListEndpoint::__construct`

Widen the type:

```php
public function __construct(
    string $path,
    string $resource,
    ?string $description = null,
    array $filters = [],
    array $includes = [],
    array $parameters = [],
    ?array $tags = null,
    ?array $security = null,
    ?string $summary = null,
    ?string $operationId = null,
    int $defaultPageSize = 15,
    int $maxPageSize = 100,
    PaginationType|array|null $paginationType = PaginationType::SIMPLE,
    bool $isInternal = false,
    bool $isBeta = false,
    ?\DateTimeInterface $deprecated = null,
    \BackedEnum|string|null $featureFlag = null,
    string|array|null $scopes = null,
    ?array $problems = null,
)
```

Default unchanged (`PaginationType::SIMPLE`). Existing callers untouched. New use:

```php
#[ListEndpoint(
    path: '/api/v1/customers/lookup',
    resource: CustomerResource::class,
    description: 'List all customers without pagination (lookup data)',
    paginationType: null,
)]
```

`defaultPageSize` and `maxPageSize` arguments are accepted-and-ignored when `paginationType` is null. No special validation.

### Runtime

No package-level runtime changes. The controller does not call `->apiPaginate(...)` and instead returns a non-paginated collection:

```php
public function lookup(): ResourceCollection
{
    return CustomerResource::collection(
        QueryBuilder::for(Customer::class)->get()
    );
}
```

Laravel's standard `ResourceCollection` produces `{data: [...]}` for non-paginated input. That matches the spec's response shape exactly.

## Generated OpenApi Output

For an endpoint declared with `paginationType: null`:

```yaml
/api/v1/customers/lookup:
  get:
    summary: 'List all customers without pagination V1'
    description: 'List all customers without pagination (lookup data)'
    operationId: 'GET::api-v1-customers-lookup'
    parameters: []   # only filter/include/etc. — NO per_page, page, cursor, x-pagination
    responses:
      '200':
        content:
          application/json:
            schema:
              properties:
                data:
                  type: array
                  items: { $ref: '#/components/schemas/CustomerResource' }
                  # NO meta, NO links, NO anyOf
              type: object
```

## Implementation

The behavioural change lives in **one method**: `ListEndpoint::mergeX`. When `paginationType` is null, both `pagination_type` and `pagination_config` are stripped from `$additionalX` before the merge — they never reach the operation's `x:` array. `PaginationResponseProcessor` is **not modified**: its existing gate `if (! isset($operation->x['pagination_type'])) continue;` already correctly skips operations whose `x:` array does not contain that key, which is exactly the new state.

```php
private function mergeX(string|array $baseX, array $additionalX): string|array
{
    if (array_key_exists('pagination_type', $additionalX) && $additionalX['pagination_type'] === null) {
        unset($additionalX['pagination_type'], $additionalX['pagination_config']);
    }

    if ($baseX === Generator::UNDEFINED && empty($additionalX['pagination_type']) && empty($additionalX)) {
        return Generator::UNDEFINED;
    }

    return array_merge($baseX === Generator::UNDEFINED ? [] : $baseX, $additionalX);
}
```

### Why the source, not the post-processor

PHP's `isset()` returns `false` for null values. The existing post-processor uses `isset($operation->x['pagination_type'])` to gate processing — which means a null value already short-circuits there. However, the keys would still leak into the final spec as `x-pagination_type: null` because the post-processor's cleanup `unset()` only runs after successful processing. Stripping at the source guarantees the keys never reach the operation, no leakage, and no post-processor changes are needed.

## Files

### Modified

- `src/OpenApi/Endpoints/ListEndpoint.php`
  - Widen `paginationType` parameter type to `PaginationType|array|null`.
  - Update `mergeX()` to strip `pagination_type` and `pagination_config` from `$additionalX` when `pagination_type` is null.

### Workbench (test surface)

- `workbench/app/Http/Controller/CustomerController.php` — add a new `lookup()` method annotated with `#[ListEndpoint(... paginationType: null)]` returning a non-paginated `ResourceCollection` of all customers.
- `tests/TestCase.php` — register the new route `GET /api/v1/customers/lookup` in `defineWebRoutes()`.
- `workbench/openapi.yml` — regenerated entry for `/api/v1/customers/lookup` with no pagination params and a `data`-only 200 response.

### Test files

- `tests/Feature/Http/Endpoints/Customers/LookupCustomersTest.php` (new) — feature tests covering:
  - All customers returned, even when count exceeds the default page size (e.g., 30 records, all 30 returned).
  - Response envelope is `{data: [...]}` with **no** `meta` and **no** `links` keys.
  - `?per_page=5` and `?page=2` query parameters are silently ignored — still all customers returned.
  - Filter/include continue to work without pagination (sanity test: filter by `country=US` returns only the US customers, envelope still `{data: [...]}`).

The existing `tests/Feature/SpecificationGeneratorTest.php` covers OpenApi regression by snapshot-comparing the generated YAML to `workbench/openapi.yml`. Once the fixture is updated to include `/api/v1/customers/lookup` with the no-pagination shape, that test gives full regression coverage for the feature's spec output.

## Out of Scope / Deferred

- A runtime helper on `QueryBuilder` for "non-paginated all-results" (`->all()` / `->getResults()`). YAGNI — `->get()` is the standard Laravel idiom and works directly.
- Treating `paginationType: []` (empty array) as equivalent to null. Pre-existing undefined behaviour; not addressed by this feature.
- A runtime guard that errors when a controller calls `->apiPaginate()` on an endpoint declared with `paginationType: null`. Spec/runtime consistency stays the developer's responsibility, same as today.
- Postgres / non-MySQL specifics. Unchanged from the rest of the package.

## Alternatives Considered

1. **New enum case `PaginationType::NONE`.** Rejected: leaks a runtime-meaningless value (`apiPaginate()` would have to handle it), pollutes arrays (`[NONE, SIMPLE]` is nonsense), and the user explicitly requested `null`.
2. **Separate `bool $paginated = true` flag on `ListEndpoint`.** Rejected: two arguments controlling related behaviour (now `paginationType: SIMPLE, paginated: false` is contradictory), more API surface for no extra value.
3. **Add the null short-circuit in `PaginationResponseProcessor`.** Rejected: PHP's `isset()` returns false for null values so the explicit `=== null` branch placed there would never be reached. Stripping at the source (`mergeX`) is one method change, requires zero post-processor modifications, and avoids any leakage of internal x-keys into the final spec.
