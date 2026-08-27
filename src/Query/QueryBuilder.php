<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\Exceptions\InvalidPageNumberQuery;
use Xentral\LaravelApi\Query\Exceptions\InvalidPageSizeQuery;
use Xentral\LaravelApi\Query\Filters\QueryBuilderFilterCollection;

/**
 * @template TModel of Model
 *
 * @extends \Spatie\QueryBuilder\QueryBuilder<TModel>
 */
class QueryBuilder extends \Spatie\QueryBuilder\QueryBuilder
{
    public function __construct(
        EloquentBuilder|Relation $subject,
        ?Request $request = null
    ) {
        $this->subject = $subject;

        // We need to override the request initialization to use our own request
        $this->request = $request
            ? QueryBuilderRequest::fromRequest($request)
            : resolve(QueryBuilderRequest::class);
    }

    public function allowedIncludes($includes): static
    {
        // Call parent to set up allowed includes
        $result = parent::allowedIncludes($includes);

        // Check if any requested includes are nested DummyIncludes and ensure parent relationships are loaded
        $this->ensureDummyIncludeParentsAreLoaded();

        return $result;
    }

    protected function ensureDummyIncludeParentsAreLoaded(): void
    {
        $requestedIncludes = $this->request->includes();

        // For each requested include that contains a dot, check if it's a DummyInclude
        $parentIncludesToAdd = $requestedIncludes
            ->filter(fn ($include) => str_contains((string) $include, '.'))
            ->map(fn ($include) =>
                // Extract parent path (everything before the last dot)
                substr((string) $include, 0, strrpos((string) $include, '.')))
            ->filter(fn ($parentPath) =>
                // Only add if the parent isn't already requested
                ! $requestedIncludes->contains($parentPath))
            ->unique();

        // Load the parent relationships
        foreach ($parentIncludesToAdd as $parentInclude) {
            $allowedInclude = $this->findInclude($parentInclude);
            if ($allowedInclude) {
                $allowedInclude->include($this);
            }
        }
    }

    public function allowedFilters($filters): static
    {
        $filters = collect(is_array($filters) ? $filters : func_get_args())
            ->map(fn ($filter) => $filter instanceof QueryBuilderFilterCollection ? $filter->getFilters() : $filter)
            ->flatten(1)
            ->toArray();

        return parent::allowedFilters($filters);
    }

    public function allowSearch(array $columns): static
    {
        $term = $this->request->input(
            config('query-builder.parameters.search', 'search')
        );

        if (! is_string($term)) {
            return $this;
        }

        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $this;
        }

        $pattern = '%'.$this->escapeLikePattern($term).'%';

        $this->getEloquentBuilder()->where(function (EloquentBuilder $query) use ($columns, $pattern) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    $lastDot = strrpos($column, '.');
                    $relation = substr($column, 0, $lastDot);
                    $field = substr($column, $lastDot + 1);

                    $query->orWhereHas($relation, function (EloquentBuilder $relationQuery) use ($field, $pattern) {
                        $qualifiedField = $relationQuery->qualifyColumn($field);
                        $relationQuery->whereRaw("{$qualifiedField} LIKE ? ESCAPE ?", [$pattern, '\\']);
                    });

                    continue;
                }

                $qualifiedColumn = $query->qualifyColumn($column);
                $query->orWhereRaw("{$qualifiedColumn} LIKE ? ESCAPE ?", [$pattern, '\\']);
            }
        });

        return $this;
    }

    public function apiPaginate(int $maxPageSize = 100, PaginationType ...$allowedTypes): Paginator|LengthAwarePaginator|CursorPaginator
    {
        $currentPage = $this->getCurrentPage();
        $perPage = $this->getPageSize($maxPageSize);
        $requestedType = $this->getRequestedPaginationType();
        $paginationType = $this->validatePaginationType($requestedType, $allowedTypes);

        return match ($paginationType) {
            PaginationType::SIMPLE => $this->simplePaginate($perPage, page: $currentPage)->withQueryString(),
            PaginationType::TABLE => $this->paginate($perPage, page: $currentPage)->withQueryString(),
            PaginationType::CURSOR => $this->cursorPaginate($perPage)->withQueryString(),
        };
    }

    private function getRequestedPaginationType(): PaginationType
    {
        $headerValue = $this->request->header('x-pagination', 'simple');

        return match (strtolower($headerValue)) {
            'table' => PaginationType::TABLE,
            'cursor' => PaginationType::CURSOR,
            default => PaginationType::SIMPLE,
        };
    }

    private function validatePaginationType(PaginationType $requested, array $allowed): PaginationType
    {
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        // If requested type is not allowed, return the first allowed type
        return $allowed[0] ?? PaginationType::SIMPLE;
    }

    private function getPageSize(int $maxPageSize): int
    {
        $requested = $this->requestedPageSizes();

        $pageSizes = [];
        foreach ($requested as $parameter => $value) {
            $pageSize = $this->toPageSize($parameter, $value);
            if ($pageSize !== null) {
                $pageSizes[$parameter] = $pageSize;
            }
        }

        if (count(array_unique($pageSizes)) > 1) {
            throw InvalidPageSizeQuery::pageSizeIsAmbiguous($requested);
        }

        $perPage = $pageSizes === [] ? 15 : reset($pageSizes);

        return min($maxPageSize, $perPage);
    }

    /**
     * The effective page size of a single sent parameter, or null when the
     * client left it unspecified. Follows AIP-158: an omitted, empty or zero
     * page size asks for the default and must not be an error, while a
     * negative one must be rejected - it reached limit(-1) in table mode,
     * which drops the LIMIT clause and returns the whole table.
     */
    private function toPageSize(string $parameter, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value) || ! is_numeric($value)) {
            throw InvalidPageSizeQuery::pageSizeMustBeAnInteger($parameter, $value);
        }

        $pageSize = (int) $value;

        if ($pageSize < 0) {
            throw InvalidPageSizeQuery::pageSizeMustNotBeNegative($parameter, $pageSize);
        }

        return $pageSize === 0 ? null : $pageSize;
    }

    /**
     * The page size spellings the client actually sent, keyed by parameter name.
     * All three are accepted, but only one effective value may be requested -
     * otherwise the page size would depend on an undocumented precedence.
     *
     * @return array<string, mixed>
     */
    private function requestedPageSizes(): array
    {
        $requested = [];

        $pageInfo = $this->request->query('page');
        if (is_array($pageInfo) && array_key_exists('size', $pageInfo)) {
            $requested['page[size]'] = $pageInfo['size'];
        }

        foreach (['per_page', 'perPage'] as $parameter) {
            if ($this->request->has($parameter)) {
                $requested[$parameter] = $this->request->input($parameter);
            }
        }

        return $requested;
    }

    private function getCurrentPage(): int
    {
        $pageInfo = $this->request->query('page');

        [$parameter, $requested] = is_array($pageInfo)
            ? ['page[number]', $pageInfo['number'] ?? null]
            : ['page', $pageInfo];

        return $this->toPageNumber($parameter, $requested) ?? 1;
    }

    /**
     * The requested page number, or null when the client left it unspecified.
     * Mirrors toPageSize(): an omitted, empty or zero page number asks for the
     * first page, while a negative or malformed one is rejected instead of
     * being silently normalised to page 1, which turns a client's paging bug
     * into an endless re-read of the first page.
     */
    private function toPageNumber(string $parameter, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value) || ! is_numeric($value)) {
            throw InvalidPageNumberQuery::pageNumberMustBeAnInteger($parameter, $value);
        }

        $pageNumber = (int) $value;

        if ($pageNumber < 0) {
            throw InvalidPageNumberQuery::pageNumberMustNotBeNegative($parameter, $pageNumber);
        }

        return $pageNumber === 0 ? null : $pageNumber;
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
