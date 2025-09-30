<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class StringOperatorFilter extends FiltersExact
{
    public function __construct(private readonly array $allowedOperators = [], private readonly ?string $enum = null) {}

    public function __invoke(Builder $query, mixed $value, string $property)
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
            throw ValidationException::withMessages([$property => "Unsupported filter operator: {$value['operator']}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, $this->allowedOperators))]);
        }

        if (! in_array($operator, $this->allowedOperators, true)) {
            throw ValidationException::withMessages([$property => "Unsupported filter operator: {$operator->value}. Valid operators are ".implode(', ', array_map(fn ($v) => $v->value, $this->allowedOperators))]);
        }

        // IS_NULL and IS_NOT_NULL don't require a value
        if (empty($value) && ! in_array($operator, [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL])) {
            return;
        }

        // IS_NULL and IS_NOT_NULL don't use the value field
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

        // Wrap value in array for processing, but remember if it was originally an array
        $wasArray = is_array($value['value']);
        $value = $this->getInternalValue(Arr::wrap($value['value']), $property);

        // If it wasn't originally an array and we only have one element, extract it
        if (! $wasArray && is_array($value) && count($value) === 1) {
            $value = $value[0];
        }

        $query->where(function (Builder $query) use ($value, $operator, $property) {
            // Handle different operators
            switch ($operator) {
                case FilterOperator::EQUALS:
                    if (is_array($value)) {
                        $query->whereIn($query->qualifyColumn($property), $value);
                    } else {
                        $query->where($query->qualifyColumn($property), $value);
                    }
                    break;
                case FilterOperator::NOT_EQUALS:
                    if (is_array($value)) {
                        $query->whereNotIn($query->qualifyColumn($property), $value);
                    } else {
                        $query->whereNot($query->qualifyColumn($property), $value);
                    }
                    break;
                case FilterOperator::IN:
                    $query->whereIn($query->qualifyColumn($property), Arr::wrap($value));
                    break;
                case FilterOperator::NOT_IN:
                    $query->whereNotIn($query->qualifyColumn($property), Arr::wrap($value));
                    break;
                case FilterOperator::CONTAINS:
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $query->orWhere($query->qualifyColumn($property), 'like', '%'.$val.'%');
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), 'like', '%'.$value.'%');
                    }
                    break;
                case FilterOperator::NOT_CONTAINS:
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $query->where($query->qualifyColumn($property), 'not like', '%'.$val.'%');
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), 'not like', '%'.$value.'%');
                    }
                    break;
                case FilterOperator::STARTS_WITH:
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $query->orWhere($query->qualifyColumn($property), 'like', $val.'%');
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), 'like', $value.'%');
                    }
                    break;
                case FilterOperator::ENDS_WITH:
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $query->orWhere($query->qualifyColumn($property), 'like', '%'.$val);
                        }
                    } else {
                        $query->where($query->qualifyColumn($property), 'like', '%'.$value);
                    }
                    break;
                case FilterOperator::LESS_THAN:
                    $query->where($query->qualifyColumn($property), '<', $value);
                    break;
                case FilterOperator::LESS_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '<=', $value);
                    break;
                case FilterOperator::GREATER_THAN:
                    $query->where($query->qualifyColumn($property), '>', $value);
                    break;
                case FilterOperator::GREATER_THAN_OR_EQUALS:
                    $query->where($query->qualifyColumn($property), '>=', $value);
                    break;
            }
        });

    }

    protected function getInternalValue(string|bool|int|array $value, string $property): string|bool|int|array
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->getInternalValue($v, $property), $value);
        }
        if (! $this->enum
            || ! enum_exists($this->enum)
            || ! defined("{$this->enum}::MAPPING")
        ) {
            return $value;
        }
        $map = $this->enum::MAPPING;

        $mappedValue = null;
        foreach ($map as $key => $val) {
            $enumValue = $val instanceof \BackedEnum ? $val->value : $val->name;
            if ($value === $enumValue) {
                $mappedValue = $key;
                break;
            }
        }
        if (! $mappedValue) {
            throw ValidationException::withMessages([
                $property => 'Invalid value: '.$value.'. Valid values are: '.implode(', ', array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v->name, $this->enum::cases()))]);
        }

        return $mappedValue;
    }

    public function allowedOperators(): array
    {
        return $this->allowedOperators;
    }

    public function enum(): ?string
    {
        return $this->enum;
    }
}
