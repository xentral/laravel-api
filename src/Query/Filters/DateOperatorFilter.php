<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class DateOperatorFilter extends FiltersExact
{
    public function __construct(private readonly string $filterName) {}

    private const array ALLOWED_OPERATORS = FilterOperator::COMPARABLE;

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

        if (empty($value['value'] ?? null)) {
            return;
        }

        if ($this->isRelationProperty($query, $property)) {
            $this->withRelationConstraint($query, $value, $property);

            return;
        }

        $this->applyFilter($query, $value, $property);
    }

    /**
     * notEquals through a relation means no related row is on the date, as in the string and number
     * filters, so a parent without related rows matches it.
     */
    protected function withRelationConstraint(Builder $query, mixed $value, string $property): void
    {
        [$relation, $property] = collect(explode('.', $property))
            ->pipe(fn (Collection $parts) => [
                $parts->except([count($parts) - 1])->implode('.'),
                $parts->last(),
            ]);

        $operator = $value['operator'] ?? null;
        $filterOperator = is_string($operator) ? FilterOperator::tryFrom($operator) : null;
        $positiveOperator = $filterOperator?->positiveForm();

        if ($positiveOperator !== null && in_array($filterOperator, self::ALLOWED_OPERATORS, true)) {
            $positiveValue = $value;
            $positiveValue['operator'] = $positiveOperator->value;

            $query->whereDoesntHave($relation, function (Builder $query) use ($property, $positiveValue) {
                $this->relationConstraints[] = $property = $query->qualifyColumn($property);
                $this->applyFilter($query, $positiveValue, $property);
            });

            return;
        }

        $query->whereHas($relation, function (Builder $query) use ($property, $value) {
            $this->relationConstraints[] = $property = $query->qualifyColumn($property);
            $this->applyFilter($query, $value, $property);
        });
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
                $parts = explode('.', $property);
                $relationProperty = array_pop($parts);
                $relationName = implode('.', $parts);

                $query->where(function (Builder $query) use ($operator, $relationName, $relationProperty) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            // The complement of isNotNull: no related row carries a date.
                            $query->whereDoesntHave($relationName, function (Builder $query) use ($relationProperty) {
                                $query->whereNotNull($query->qualifyColumn($relationProperty))
                                    ->where($query->qualifyColumn($relationProperty), '!=', '0000-00-00 00:00:00')
                                    ->where($query->qualifyColumn($relationProperty), '!=', '0000-00-00');
                            });
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $query->whereNotNull($query->qualifyColumn($relationProperty))
                                    ->where($query->qualifyColumn($relationProperty), '!=', '0000-00-00 00:00:00')
                                    ->where($query->qualifyColumn($relationProperty), '!=', '0000-00-00');
                            });
                            break;
                    }
                });
            } else {
                $query->where(function (Builder $query) use ($operator, $property) {
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereNull($query->qualifyColumn($property))
                                ->orWhere($query->qualifyColumn($property), '0000-00-00 00:00:00')
                                ->orWhere($query->qualifyColumn($property), '0000-00-00');
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereNotNull($query->qualifyColumn($property))
                                ->where($query->qualifyColumn($property), '!=', '0000-00-00 00:00:00')
                                ->where($query->qualifyColumn($property), '!=', '0000-00-00');
                            break;
                    }
                });
            }

            return;
        }

        // Wrap value in array for processing, but remember if it was originally an array
        $wasArray = is_array($value['value']);
        $filterValue = Arr::wrap($value['value']);

        $this->validateDateValues($filterValue);

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

            // A row without a date is not on the given day, so notEquals keeps it.
            if ($operator === FilterOperator::NOT_EQUALS) {
                $query->orWhereNull($query->qualifyColumn($property));
            }
        });
    }

    private function validateDateValues(array $values): void
    {
        foreach ($values as $value) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                throw ValidationException::withMessages([
                    $this->filterName => "The filter value '{$value}' for '{$this->filterName}' is not a valid date. Expected format: Y-m-d.",
                ]);
            }
        }
    }

    public function allowedOperators(): array
    {
        return self::ALLOWED_OPERATORS;
    }
}
