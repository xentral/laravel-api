<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class NumberOperatorFilter extends FiltersExact
{
    private const NEGATIVE_TO_POSITIVE_MAP = [
        'notEquals' => 'equals',
    ];

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

    public function __construct(private readonly string $filterName) {}

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

    protected function withRelationConstraint(Builder $query, mixed $value, string $property): void
    {
        [$relation, $property] = collect(explode('.', $property))
            ->pipe(fn (Collection $parts) => [
                $parts->except([count($parts) - 1])->implode('.'),
                $parts->last(),
            ]);

        $operator = $value['operator'] ?? null;
        $isNegativeOperator = $operator && isset(self::NEGATIVE_TO_POSITIVE_MAP[$operator]);

        if ($isNegativeOperator) {
            $positiveValue = $value;
            $positiveValue['operator'] = self::NEGATIVE_TO_POSITIVE_MAP[$operator];

            $query->whereDoesntHave($relation, function (Builder $query) use ($property, $positiveValue) {
                $this->relationConstraints[] = $property = $query->qualifyColumn($property);
                $this->applyFilter($query, $positiveValue, $property, skipValidation: true);
            });
        } else {
            $query->whereHas($relation, function (Builder $query) use ($property, $value) {
                $this->relationConstraints[] = $property = $query->qualifyColumn($property);
                $this->applyFilter($query, $value, $property);
            });
        }
    }

    private function applyFilter(Builder $query, array $value, string $property, bool $skipValidation = false): void
    {
        try {
            $operator = FilterOperator::from($value['operator']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $this->filterName => "Unsupported filter operator: {$value['operator']}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, self::ALLOWED_OPERATORS)),
            ]);
        }

        if (! $skipValidation && ! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw ValidationException::withMessages([
                $this->filterName => "Unsupported filter operator: {$operator->value}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, self::ALLOWED_OPERATORS)),
            ]);
        }

        if (empty($value) && ! in_array($operator, [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL])) {
            return;
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

        $wasArray = is_array($value['value']);
        $filterValues = Arr::wrap($value['value']);

        $this->validateNumericValues($filterValues);

        if (! $wasArray && count($filterValues) === 1) {
            $filterValues = $filterValues[0];
        }

        $query->where(function (Builder $query) use ($filterValues, $operator, $property) {
            switch ($operator) {
                case FilterOperator::EQUALS:
                    if (is_array($filterValues)) {
                        $query->whereIn($query->qualifyColumn($property), $filterValues);
                    } else {
                        $query->where($query->qualifyColumn($property), $filterValues);
                    }
                    break;
                case FilterOperator::NOT_EQUALS:
                    if (is_array($filterValues)) {
                        $query->whereNotIn($query->qualifyColumn($property), $filterValues);
                    } else {
                        $query->whereNot($query->qualifyColumn($property), $filterValues);
                    }
                    break;
                case FilterOperator::LESS_THAN:
                    $query->where($query->qualifyColumn($property), '<', $filterValues);
                    break;
                case FilterOperator::LESS_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '<=', $filterValues);
                    break;
                case FilterOperator::GREATER_THAN:
                    $query->where($query->qualifyColumn($property), '>', $filterValues);
                    break;
                case FilterOperator::GREATER_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '>=', $filterValues);
                    break;
            }
        });
    }

    private function validateNumericValues(array $values): void
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $this->filterName => "The filter value '{$value}' for '{$this->filterName}' is not a valid number.",
                ]);
            }
        }
    }

    public function allowedOperators(): array
    {
        return self::ALLOWED_OPERATORS;
    }
}
