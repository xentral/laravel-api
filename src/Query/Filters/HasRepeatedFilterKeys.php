<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Applies a filter key that appeared more than once in the request.
 *
 * QueryBuilderRequest collapses a repeated key into a list of {operator, value}
 * entries, so each entry has to be applied in turn - conjunctively, since every
 * entry narrows the same query.
 *
 * @phpstan-require-implements Filter
 */
trait HasRepeatedFilterKeys
{
    protected function applyRepeatedFilters(Builder $query, mixed $value, string $property): bool
    {
        if (! isset($value[0]) || ! is_array($value[0])) {
            return false;
        }

        foreach ($value as $filter) {
            $this->__invoke($query, $filter, $property);
        }

        return true;
    }
}
