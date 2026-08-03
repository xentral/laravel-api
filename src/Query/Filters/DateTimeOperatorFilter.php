<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\FiltersExact;

class DateTimeOperatorFilter extends FiltersExact
{
    public function __construct(private readonly string $filterName) {}

    private const LEGACY_ZERO_DATETIME = '0000-00-00 00:00:00';

    private const LEGACY_ZERO_DATE = '0000-00-00';

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
                                    $column = $query->qualifyColumn($relationProperty);
                                    $query->whereNull($column)
                                        ->orWhere($column, self::LEGACY_ZERO_DATETIME)
                                        ->orWhere($column, self::LEGACY_ZERO_DATE);
                                });
                            });
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereHas($relationName, function (Builder $query) use ($relationProperty) {
                                $column = $query->qualifyColumn($relationProperty);
                                $query->whereNotNull($column)
                                    ->where($column, '!=', self::LEGACY_ZERO_DATETIME)
                                    ->where($column, '!=', self::LEGACY_ZERO_DATE);
                            });
                            break;
                    }
                });
            } else {
                $query->where(function (Builder $query) use ($operator, $property) {
                    $column = $query->qualifyColumn($property);
                    switch ($operator) {
                        case FilterOperator::IS_NULL:
                            $query->whereNull($column)
                                ->orWhere($column, self::LEGACY_ZERO_DATETIME)
                                ->orWhere($column, self::LEGACY_ZERO_DATE);
                            break;
                        case FilterOperator::IS_NOT_NULL:
                            $query->whereNotNull($column)
                                ->where($column, '!=', self::LEGACY_ZERO_DATETIME)
                                ->where($column, '!=', self::LEGACY_ZERO_DATE);
                            break;
                    }
                });
            }

            return;
        }

        if (empty($value['value'])) {
            return;
        }

        $filterValue = Arr::wrap($value['value']);

        $this->validateDateTimeValues($filterValue);

        $filterValue = $this->resolveFilterValues($filterValue);

        $query->where(function (Builder $query) use ($filterValue, $operator, $property) {
            $column = $query->qualifyColumn($property);

            foreach ($filterValue as $val) {
                switch ($operator) {
                    case FilterOperator::EQUALS:
                        if (is_array($val)) {
                            $query->orWhere(function (Builder $query) use ($column, $val) {
                                $query->where($column, '>=', $val['start'])->where($column, '<', $val['end']);
                            });
                        } else {
                            $query->orWhere($column, '=', $val);
                        }
                        break;

                    case FilterOperator::NOT_EQUALS:
                        if (is_array($val)) {
                            $query->where(function (Builder $query) use ($column, $val) {
                                $query->where($column, '<', $val['start'])->orWhere($column, '>=', $val['end']);
                            });
                        } else {
                            $query->where($column, '!=', $val);
                        }
                        break;

                    case FilterOperator::LESS_THAN:
                        $query->where($column, '<', is_array($val) ? $val['start'] : $val);
                        break;

                    case FilterOperator::LESS_THAN_OR_EQUALS:
                        if (is_array($val)) {
                            $query->where($column, '<', $val['end']);
                        } else {
                            $query->where($column, '<=', $val);
                        }
                        break;

                    case FilterOperator::GREATER_THAN:
                        if (is_array($val)) {
                            $query->where($column, '>=', $val['end']);
                        } else {
                            $query->where($column, '>', $val);
                        }
                        break;

                    case FilterOperator::GREATER_THAN_OR_EQUALS:
                        $query->where($column, '>=', is_array($val) ? $val['start'] : $val);
                        break;
                }
            }
        });
    }

    /**
     * Resolves each value to an app-timezone wall clock instant, or to a
     * day interval (start inclusive, end exclusive) for plain Y-m-d dates.
     *
     * @param  list<string>  $values
     * @return list<string|array{start: string, end: string}>
     */
    private function resolveFilterValues(array $values): array
    {
        $appTimezone = new \DateTimeZone(config('app.timezone'));

        return array_map(function (string $value) use ($appTimezone): string|array {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $appTimezone);
            if ($day instanceof \DateTimeImmutable && $day->format('Y-m-d') === $value) {
                return [
                    'start' => $day->format('Y-m-d H:i:s'),
                    'end' => $day->modify('+1 day')->format('Y-m-d H:i:s'),
                ];
            }

            return (new \DateTimeImmutable($value, $appTimezone))
                ->setTimezone($appTimezone)
                ->format('Y-m-d H:i:s');
        }, $values);
    }

    private function validateDateTimeValues(array $values): void
    {
        $formats = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'];

        foreach ($values as $value) {
            $valid = false;
            foreach ($formats as $format) {
                $parsed = \DateTimeImmutable::createFromFormat($format, $value);
                if ($parsed !== false && $parsed->format($format) === $value) {
                    $valid = true;
                    break;
                }
            }

            if (! $valid) {
                throw ValidationException::withMessages([
                    $this->filterName => "The filter value '{$value}' for '{$this->filterName}' is not a valid datetime. Expected format: Y-m-d\TH:i:sP, Y-m-d\TH:i:s, Y-m-d H:i:s or Y-m-d.",
                ]);
            }
        }
    }

    public function allowedOperators(): array
    {
        return self::ALLOWED_OPERATORS;
    }
}
