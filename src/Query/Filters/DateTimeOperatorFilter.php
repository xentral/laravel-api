<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class DateTimeOperatorFilter extends FiltersExact
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

        if (in_array($operator, [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL])) {
            if ($this->isRelationProperty($query, $property)) {
                $parts = explode('.', $property);
                $relationProperty = array_pop($parts);
                $relationName = implode('.', $parts);

                $query->where(function (Builder $query) use ($operator, $relationName, $relationProperty) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $query->where(function (Builder $query) use ($relationProperty) {
                                    $query->whereNull($query->qualifyColumn($relationProperty))
                                        ->orWhere($query->qualifyColumn($relationProperty), '0000-00-00 00:00:00');
                                });
                            });
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $query->whereNotNull($query->qualifyColumn($relationProperty))
                                    ->where($query->qualifyColumn($relationProperty), '!=', '0000-00-00 00:00:00');
                            });
                            break;
                    }
                });
            } else {
                $query->where(function (Builder $query) use ($operator, $property) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereNull($query->qualifyColumn($property))
                                ->orWhere($query->qualifyColumn($property), '0000-00-00 00:00:00');
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereNotNull($query->qualifyColumn($property))
                                ->where($query->qualifyColumn($property), '!=', '0000-00-00 00:00:00');
                            break;
                    }
                });
            }

            return;
        }

        if (empty($value['value'])) {
            return;
        }

        $wasArray = is_array($value['value']);
        $filterValue = Arr::wrap($value['value']);

        if (! $wasArray && count($filterValue) === 1) {
            $filterValue = $filterValue[0];
        }

        $query->where(function (Builder $query) use ($filterValue, $operator, $property) {
            switch ($operator) {
                case FilterOperator::EQUALS:
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $val) {
                            $query->orWhere($query->qualifyColumn($property), '=', $val);
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), '=', $filterValue);
                    }
                    break;

                case FilterOperator::NOT_EQUALS:
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $val) {
                            $query->where($query->qualifyColumn($property), '!=', $val);
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), '!=', $filterValue);
                    }
                    break;

                case FilterOperator::LESS_THAN:
                    $query->where($query->qualifyColumn($property), '<', $filterValue);
                    break;

                case FilterOperator::LESS_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '<=', $filterValue);
                    break;

                case FilterOperator::GREATER_THAN:
                    $query->where($query->qualifyColumn($property), '>', $filterValue);
                    break;

                case FilterOperator::GREATER_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '>=', $filterValue);
                    break;
            }
        });
    }

    public function allowedOperators(): array
    {
        return self::ALLOWED_OPERATORS;
    }
}
