<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\Filters\QueryBuilderFilterCollection;

class QueryBuilder extends \Spatie\QueryBuilder\QueryBuilder
{
    public function __construct(
        protected EloquentBuilder|Relation $subject,
        ?Request $request = null
    ) {
        // We need to override the request initialization to use our own request
        $this->request = $request
            ? QueryBuilderRequest::fromRequest($request)
            : app(QueryBuilderRequest::class);
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

    public function apiPaginate(int $maxPageSize = 100, PaginationType ...$allowedTypes): Paginator|LengthAwarePaginator|CursorPaginator
    {
        $currentPage = $this->getCurrentPage();
        $perPage = $this->getPageSize($maxPageSize);
        $requestedType = $this->getRequestedPaginationType();
        $paginationType = $this->validatePaginationType($requestedType, $allowedTypes);

        return match ($paginationType) {
            PaginationType::SIMPLE => $this->simplePaginate($perPage, page: $currentPage)->withQueryString(),
            PaginationType::TABLE => $this->paginate($perPage, page: $currentPage)->withQueryString()->withQueryString(),
            PaginationType::CURSOR => $this->cursorPaginate($perPage)->withQueryString()->withQueryString(),
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
        $pageInfo = $this->request->query('page');
        if (is_array($pageInfo)) {
            return min($maxPageSize, (int) $pageInfo['size']);
        }

        $perPage = $this->request->integer('per_page', $this->request->integer('perPage', 15));

        return min($maxPageSize, $perPage);
    }

    private function getCurrentPage(): int
    {
        $pageInfo = $this->request->query('page');
        if (is_array($pageInfo)) {
            return intval($pageInfo['number'] ?? 1);
        }

        return intval($pageInfo ?? 1);
    }
}
