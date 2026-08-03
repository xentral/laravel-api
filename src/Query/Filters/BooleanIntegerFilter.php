<?php
declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filters a legacy integer flag column by truthiness rather than by an exact 1.
 *
 * Consumers expose such columns as `(bool) $row->column`, so the filter mirrors
 * that cast: any value greater than zero is true, zero and NULL are false.
 */
class BooleanIntegerFilter implements Filter
{
    use HasRepeatedFilterKeys;

    public function __construct(private readonly string $filterName) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($this->applyRepeatedFilters($query, $value, $property)) {
            return;
        }

        $operator = $value['operator'] ?? 'equals';
        $isTruthy = FilterValue::toBool($value['value'], $this->filterName);

        $matchesTruthy = match ($operator) {
            'equals' => $isTruthy,
            'notEquals' => ! $isTruthy,
            default => throw ValidationException::withMessages([$this->filterName => "Unsupported operator: {$operator}. Use 'equals' or 'notEquals'."]),
        };

        $column = $query->qualifyColumn($property);

        if ($matchesTruthy) {
            $query->where($column, '>', 0);

            return;
        }

        $query->where(function (Builder $query) use ($column): void {
            $query->where($column, 0)->orWhereNull($column);
        });
    }
}
