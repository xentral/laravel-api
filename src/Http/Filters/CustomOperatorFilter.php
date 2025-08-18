<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;
use Xentral\LaravelApi\Enum\FilterOperator;

class CustomOperatorFilter extends FiltersExact
{
    public function __construct(private readonly array $allowedOperators = [], private readonly ?string $enum = null) {}

    public function __invoke(Builder $query, mixed $value, string $property)
    {
        if ($this->isRelationProperty($query, $property)) {
            $this->withRelationConstraint($query, $value, $property);

            return;
        }

        $operator = FilterOperator::from($value['operator']);
        if (! in_array($operator, $this->allowedOperators)) {
            throw ValidationException::withMessages([$property => "Unsupported filter operator: {$operator->value}"]);
        }

        if (empty($value)) {
            return;
        }
        if (is_array($value['value']) && ! in_array($operator, [FilterOperator::IN, FilterOperator::NOT_IN])) {
            throw ValidationException::withMessages([$property => "Unsupported filter operator: {$operator->value}. Only in and notIn are allowed for multiple values"]);
        }
        $value = $this->getInternalValue(Arr::wrap($value['value']), $property);

        $query->where(function (Builder $query) use ($value, $operator, $property) {
            // Handle different operators
            switch ($operator) {
                case FilterOperator::EQUALS:
                    $query->where($query->qualifyColumn($property), $value);
                    break;
                case FilterOperator::NOT_EQUALS:
                    $query->whereNot($query->qualifyColumn($property), $value);
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
                    $query->where($query->qualifyColumn($property), 'not like', '%'.$value.'%');
                    break;
                case FilterOperator::STARTS_WITH:
                    $query->where($query->qualifyColumn($property), 'like', $value.'%');
                    break;
                case FilterOperator::ENDS_WITH:
                    $query->where($query->qualifyColumn($property), 'like', '%'.$value);
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
                case FilterOperator::IS_NULL:
                    $query->whereNull($query->qualifyColumn($property));
                    break;
                case FilterOperator::IS_NOT_NULL:
                    $query->whereNotNull($query->qualifyColumn($property));
                    break;
            }
        });

    }

    protected function getInternalValue(string|bool|array $value, string $property): string|bool|array
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
            if ($value === $val->value) {
                $mappedValue = $key;
                break;
            }
        }
        if (! $mappedValue) {
            throw ValidationException::withMessages([
                $property => 'Invalid value: '.$value.'. Valid values are: '.implode(', ', array_map(fn ($v) => $v->value, $this->enum::cases()))]);
        }

        return $mappedValue;
    }
}
