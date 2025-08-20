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

    public function apiPaginate(PaginationType ...$allowedTypes): Paginator|LengthAwarePaginator|CursorPaginator
    {
        $perPage = min(100, $this->request->integer('per_page', 15));
        $requestedType = $this->getRequestedPaginationType();
        $paginationType = $this->validatePaginationType($requestedType, $allowedTypes);

        return match ($paginationType) {
            PaginationType::SIMPLE => $this->simplePaginate($perPage),
            PaginationType::TABLE => $this->paginate($perPage),
            PaginationType::CURSOR => $this->cursorPaginate($perPage),
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

    public function allowedFilters($filters): static
    {
        $filters = collect(is_array($filters) ? $filters : func_get_args())
            ->map(fn ($filter) => $filter instanceof QueryBuilderFilterCollection ? $filter->getFilters() : $filter)
            ->flatten(1)
            ->toArray();

        return parent::allowedFilters($filters);
    }
}
