<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class DateOperatorFilter extends FiltersExact
{
    private const ALLOWED_OPERATORS = [
        FilterOperator::EQUALS,
        FilterOperator::NOT_EQUALS,
        FilterOperator::LESS_THAN,
        FilterOperator::LESS_THAN_OR_EQUALS,
        FilterOperator::GREATER_THAN,
        FilterOperator::GREATER_THAN_OR_EQUALS,
        FilterOperator::IS_NULL,
        FilterOperator::IS_NOT_NULL,
    ];

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (isset($value[0]) && is_array($value[0])) {
            foreach ($value as $filter) {
                $this->__invoke($query, $filter, $property);
            }

            return;
        }

        if (is_array($value) && isset($value['operator']) && in_array($value['operator'], ['isNull', 'isNotNull'])) {
            $this->applyFilter($query, $value, $property);

            return;
        }

        if ($this->isRelationProperty($query, $property)) {
            $this->withRelationConstraint($query, $value, $property);

            return;
        }

        $this->applyFilter($query, $value, $property);
    }

    private function applyFilter(Builder $query, array $value, string $property): void
    {
        try {
            $operator = FilterOperator::from($value['operator']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $property => "Unsupported filter operator: {$value['operator']}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, self::ALLOWED_OPERATORS)),
            ]);
        }

        if (! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw ValidationException::withMessages([
                $property => "Unsupported filter operator: {$operator->value}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, self::ALLOWED_OPERATORS)),
            ]);
        }

        // IS_NULL and IS_NOT_NULL don't require a value
        if (in_array($operator, [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL])) {
            // Handle relation properties (e.g., customer.phone)
            if ($this->isRelationProperty($query, $property)) {
                [$relationName, $relationProperty] = collect(explode('.', $property))
                    ->pipe(fn ($parts) => [
                        $parts->except(count($parts) - 1)->implode('.'),
                        $parts->last(),
                    ]);

                $query->where(function (Builder $query) use ($operator, $relationName, $relationProperty) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $query->whereNull($query->qualifyColumn($relationProperty));
                            });
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $query->whereNotNull($query->qualifyColumn($relationProperty));
                            });
                            break;
                    }
                });
            } else {
                $query->where(function (Builder $query) use ($operator, $property) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereNull($query->qualifyColumn($property));
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereNotNull($query->qualifyColumn($property));
                            break;
                    }
                });
            }

            return;
        }

        if (empty($value['value'])) {
            return;
        }

        if (is_array($value['value']) && ! in_array($operator, [FilterOperator::IN, FilterOperator::NOT_IN])) {
            throw ValidationException::withMessages([
                $property => "Unsupported filter operator: {$operator->value}. Only in and notIn are allowed for multiple values",
            ]);
        }

        // Wrap value in array for processing, but remember if it was originally an array
        $wasArray = is_array($value['value']);
        $filterValue = Arr::wrap($value['value']);

        // If it wasn't originally an array and we only have one element, extract it
        if (! $wasArray && count($filterValue) === 1) {
            $filterValue = $filterValue[0];
        }

        $query->where(function (Builder $query) use ($filterValue, $operator, $property) {
            switch ($operator) {
                case FilterOperator::EQUALS:
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $val) {
                            $query->orWhereDate($query->qualifyColumn($property), '=', $val);
                        }
                    } else {
                        $query->whereDate($query->qualifyColumn($property), '=', $filterValue);
                    }
                    break;

                case FilterOperator::NOT_EQUALS:
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $val) {
                            $query->whereDate($query->qualifyColumn($property), '!=', $val);
                        }
                    } else {
                        $query->whereDate($query->qualifyColumn($property), '!=', $filterValue);
                    }
                    break;

                case FilterOperator::LESS_THAN:
                    $query->whereDate($query->qualifyColumn($property), '<', $filterValue);
                    break;

                case FilterOperator::LESS_THAN_OR_EQUALS:
                    $query->whereDate($query->qualifyColumn($property), '<=', $filterValue);
                    break;

                case FilterOperator::GREATER_THAN:
                    $query->whereDate($query->qualifyColumn($property), '>', $filterValue);
                    break;

                case FilterOperator::GREATER_THAN_OR_EQUALS:
                    $query->whereDate($query->qualifyColumn($property), '>=', $filterValue);
                    break;
            }
        });
    }

    public function allowedOperators(): array
    {
        return self::ALLOWED_OPERATORS;
    }
}
