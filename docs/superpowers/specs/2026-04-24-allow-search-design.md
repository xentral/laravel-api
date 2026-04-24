# `allowSearch()` — Multi-Column OR Search

Date: 2026-04-24
Status: Accepted

## Summary

Add a `search` query parameter to any list endpoint by calling a new `allowSearch(array $columns)` method on `Xentral\LaravelApi\Query\QueryBuilder`. A single request value matches via `LIKE '%term%'` across all configured columns, joined with `OR`. Relation columns are supported via dot-notation, consistent with the existing filter API.

## Motivation

Current filter API is deliberately structured: each filter is a `{key, op, value}` tuple targeting one column. That is correct for advanced querying but heavy for the common UX of a single "search box" that should find a record by any of several likely fields (invoice number, customer name, customer email, line-item product name, …). Consumers today have to hand-roll a custom `AllowedFilter::callback()` per endpoint. A first-class `allowSearch()` removes that duplication.

## Public API

### Runtime

```php
use Xentral\LaravelApi\Query\QueryBuilder;

QueryBuilder::for(Invoice::class)
    ->allowedFilters([...])
    ->allowSearch([
        'invoice_number',
        'customer.name',
        'customer.email',
        'lineItems.product_name',
    ])
    ->allowedIncludes([...])
    ->apiPaginate(...);
```

Signature:

```php
public function allowSearch(array $columns): static
```

- **Input:** non-empty array of strings. Direct column names or dotted relation paths (`{relation}.{column}`, recursively).
- **Idempotency:** second call replaces — matches `Spatie\QueryBuilder\QueryBuilder::allowedFilters()` behaviour.
- **Return:** `static`, for fluent chaining.
- **Name:** verb phrase `allowSearch`, not `allowedSearch` (explicitly chosen — there is no plural "allowed searches" concept; the method turns a feature on for one list of columns).

### OpenApi

```php
use Xentral\LaravelApi\OpenApi\SearchParameter;

#[ListEndpoint(
    path: '/api/v1/invoices',
    resource: InvoiceResource::class,
    parameters: [
        new FilterParameter([...]),
        new SearchParameter,
    ],
)]
public function index(): ResourceCollection { ... }
```

`SearchParameter` extends `OpenApi\Annotations\Parameter` and produces:

```yaml
- name: search
  in: query
  required: false
  schema:
    type: string
  description: Full-text-style search across predefined columns.
```

Constructor:

```php
public function __construct(
    ?string $description = null,
    ?bool $deprecated = null,
    ?array $x = null,
)
```

No `columns:` argument — spec stays clean and avoids leaking internal column choices. The `description:` argument lets a specific endpoint override the default blurb if it wants to.

## Request Contract

- **Parameter name:** resolved via `config('query-builder.parameters.search', 'search')`, matching the pattern used by `filter`, `sort`, `include`, `fields`. Default: `search`.
- **Type:** single string.
- **Absent parameter:** no-op (no `WHERE` clause added).
- **Empty / whitespace-only value:** trimmed, then no-op. Same user-visible behaviour as "absent".
- **`allowSearch()` never called but `?search=` present:** silently ignored. The parameter is additive convenience, not a declared contract (unlike `filter`/`sort` keys, which throw when unknown).

## SQL Shape

Given `allowSearch(['invoice_number', 'customer.name', 'customer.email'])` and `?search=acme`, the package adds one grouped `WHERE`:

```sql
WHERE (
  `invoices`.`invoice_number` LIKE '%acme%'
  OR EXISTS (
    SELECT * FROM `customers`
    WHERE `invoices`.`customer_id` = `customers`.`id`
      AND `customers`.`name` LIKE '%acme%'
  )
  OR EXISTS (
    SELECT * FROM `customers`
    WHERE `invoices`.`customer_id` = `customers`.`id`
      AND `customers`.`email` LIKE '%acme%'
  )
)
```

Equivalent Laravel builder code:

```php
$query->where(function (Builder $q) use ($escaped) {
    $q->orWhere($q->qualifyColumn('invoice_number'), 'like', "%{$escaped}%");
    $q->orWhereHas('customer', fn (Builder $r) => $r->where($r->qualifyColumn('name'), 'like', "%{$escaped}%"));
    $q->orWhereHas('customer', fn (Builder $r) => $r->where($r->qualifyColumn('email'), 'like', "%{$escaped}%"));
});
```

- The outer `where(function () { … })` isolates the OR group from other AND-ed conditions (filters, base constraints).
- `qualifyColumn` used throughout to avoid ambiguous-column errors when joins are present.
- Relation paths split on the **last** dot: `lineItems.customFields.key` → `orWhereHas('lineItems.customFields', fn ($q) => $q->where('key', …))`. Laravel's `whereHas` supports dotted relations natively.
- Same-relation columns each produce an independent `EXISTS` subquery. Acceptable for v1. If profiling shows this is hot, a follow-up can collapse same-relation columns into a single `orWhereHas` with a nested `orWhere` group.

## LIKE Escaping

The search term is escaped before wrapping in `%…%`:

- `\` → `\\`
- `%` → `\%`
- `_` → `\_`

Order matters (backslash first). A request for `?search=50%` matches records literally containing `50%`, not "anything starting with 50". Prevents a stray `%` or `_` from widening results unexpectedly.

MySQL's default `utf8mb4_unicode_ci` collation is case-insensitive, so no `LOWER()` is needed. The package is MySQL-targeted (existing filters rely on the same behaviour).

## Integration With Existing Features

- **Filters:** AND-combined. `?search=acme&filter=[{"key":"status","op":"equals","value":"paid"}]` returns paid invoices whose search term matches.
- **Sort:** unaffected.
- **Pagination:** unaffected. `?search=acme&per_page=5` works.
- **Includes:** unaffected.

## Implementation Plan (overview — detailed plan follows in `writing-plans`)

### New files

- `src/Query/QueryBuilder.php` — add `allowSearch(array $columns): static` method and a private LIKE-escape helper. No new class; logic inlined into `QueryBuilder`.
- `src/OpenApi/SearchParameter.php` — new attribute class extending `OpenApi\Annotations\Parameter`.

### Modified files

- `workbench/app/Http/Controller/InvoiceController.php` — add `allowSearch([...])` to `index()` and `new SearchParameter` to the `#[ListEndpoint]` `parameters:` array. Covers end-to-end behaviour in tests.

### Test files

- `tests/Feature/Http/Endpoints/Invoices/SearchInvoicesTest.php` (new) — covers:
  - Match by direct column.
  - Match by single-level relation column.
  - Match by multi-level relation column (`lineItems.product_name`).
  - Cross-column OR returns union of matches.
  - Empty result when no records match.
  - Empty / whitespace-only `?search=` is a no-op.
  - Literal `%` in the term matches `%` in data (wildcard escaped).
  - Literal `_` in the term matches `_` in data (wildcard escaped).
  - Combined with filter: search AND filter are ANDed.
  - Combined with sort + pagination still works.
  - `?search=` on a non-configured endpoint is silently ignored.
- Extend `tests/Feature/SpecificationGeneratorTest.php` — confirm a route annotated with `SearchParameter` produces the expected `search` parameter (`in: query`, `type: string`) in the generated spec.

The LIKE-escape helper is a private method on `QueryBuilder`; its correctness is covered by the two escape feature tests above (literal `%` and literal `_` cases) rather than via a unit test on the private method.

## Out of Scope / Deferred

- Multi-token whitespace splitting (e.g., `?search=acme corp` matching records where both tokens appear).
- Per-column match overrides (`startsWith` / `exact`).
- Full-text / `MATCH…AGAINST` support.
- Auto-documenting searchable columns in the generated spec description.
- Postgres `ILIKE` support.
- `allowSearch()` merging across calls.

All of these are non-breaking future additions.

## Alternatives Considered

1. **Reuse Spatie's filter pipeline via `AllowedFilter::callback()`.** Rejected: `search` is a top-level query parameter, not a `filter[]` key; piggybacking would leak a phantom `_search` filter into `$allowedFilters` and still require overriding request parsing.
2. **Eloquent scope / builder macro.** Rejected: breaks encapsulation — `allowSearch()` on the QueryBuilder would be disconnected from the actual SQL application.
