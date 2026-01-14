<?php
declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\Filter;

class BooleanIntegerFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (isset($value[0]) && is_array($value[0])) {
            foreach ($value as $filter) {
                $this->__invoke($query, $filter, $property);
            }

            return;
        }

        $operator = $value['operator'] ?? 'equals';
        $filterValue = $value['value'];
        $dbValue = $this->toDbValue($filterValue, $property);

        match ($operator) {
            'equals' => $query->where($query->qualifyColumn($property), $dbValue),
            'notEquals' => $query->whereNot($query->qualifyColumn($property), $dbValue),
            default => throw ValidationException::withMessages([$property => "Unsupported operator: {$operator}. Use 'equals' or 'notEquals'."]),
        };
    }

    private function toDbValue(mixed $value, string $property): int
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return 1;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return 0;
        }
        throw ValidationException::withMessages([$property => "Invalid value: {$value}. Valid values are: true, false."]);
    }
}
