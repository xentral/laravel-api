<?php
declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

/**
 * Filters a legacy integer flag column by truthiness rather than by an exact 1.
 *
 * Consumers expose such columns as `(bool) $row->column`, so the filter mirrors
 * that cast: any value greater than zero is true, zero and NULL are false. A
 * dotted key naming a relation filters the related row.
 */
class BooleanIntegerFilter extends FiltersExact
{
    use HasRepeatedFilterKeys;

    public function __construct(private readonly string $filterName)
    {
        parent::__construct();
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($this->applyRepeatedFilters($query, $value, $property)) {
            return;
        }

        if ($this->isRelationProperty($query, $property)) {
            $this->withRelationConstraint($query, $value, $property);

            return;
        }

        $operator = $value['operator'] ?? 'equals';
        $isTruthy = FilterValue::toBool($value['value'] ?? null, $this->filterName);

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

    /**
     * @return list<FilterOperator>
     */
    public function allowedOperators(): array
    {
        return FilterOperator::BOOLEAN;
    }
}
