<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Xentral\LaravelApi\Query\Filters\FilterGroup;
use Xentral\LaravelApi\Query\Filters\FilterGroupOperator;

class QueryBuilderRequest extends \Spatie\QueryBuilder\QueryBuilderRequest
{
    public function filters(): Collection
    {
        $group = $this->filterGroup();

        if ($group !== null) {
            $filters = collect();

            foreach ($group->conditions as $condition) {
                $this->mergeFilterValue($filters, $condition['key'], $condition['filter']);
            }

            return $filters;
        }

        try {
            $filters = collect();

            foreach ($this->rawFilterParts() as $filter) {
                $this->mergeFilterValue($filters, $filter['key'], [
                    'operator' => $filter['op'],
                    'value' => $this->getFilterValue($filter['value'] ?? null),
                ]);
            }

            return $filters;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
        }
    }

    /**
     * The requested filter as a boolean group, or null when the request uses
     * the flat filter list - which is an implicit `and` group.
     */
    public function filterGroup(): ?FilterGroup
    {
        $filterParts = $this->rawFilterParts();

        return $this->isGroupShape($filterParts) ? $this->parseGroup($filterParts) : null;
    }

    /**
     * The filter parameter as an array, in whichever wire form it arrived:
     * a deepObject, or a JSON string for clients such as SwaggerUI.
     *
     * @return array<mixed>
     */
    private function rawFilterParts(): array
    {
        $filterParameterName = config('query-builder.parameters.filter', 'filter');

        $filterParts = $this->getRequestData($filterParameterName, []);

        if (is_string($filterParts)) {
            try {
                $filterParts = json_decode($filterParts, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return [];
            }

            if (isset($filterParts['key'])) {
                // A single filter object is shorthand for a one element list.
                $filterParts = [$filterParts];
            }
        }

        if (! is_array($filterParts)) {
            throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
        }

        return $filterParts;
    }

    /**
     * A group carries its conditions under `conditions`; the `key` guard keeps
     * a single filter object that happens to filter on a `conditions` field
     * out of the group path.
     *
     * @param  array<mixed>  $filterParts
     */
    private function isGroupShape(array $filterParts): bool
    {
        return array_key_exists('conditions', $filterParts) && ! array_key_exists('key', $filterParts);
    }

    /** @param array<mixed> $filterParts */
    private function parseGroup(array $filterParts): FilterGroup
    {
        $validOperators = implode(', ', array_column(FilterGroupOperator::cases(), 'value'));

        if (! array_key_exists('op', $filterParts)) {
            throw ValidationException::withMessages([
                'filter' => 'A filter group requires an operator. Valid operators are '.$validOperators.'.',
            ]);
        }

        $operator = $filterParts['op'];
        $groupOperator = is_string($operator) ? FilterGroupOperator::tryFrom($operator) : null;

        if ($groupOperator === null) {
            throw ValidationException::withMessages(['filter' => sprintf(
                'Invalid filter group operator: %s. Valid operators are %s.',
                is_scalar($operator) ? (string) $operator : gettype($operator),
                $validOperators,
            )]);
        }

        if (array_diff_key($filterParts, array_flip(['op', 'conditions'])) !== []) {
            throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
        }

        $conditions = $filterParts['conditions'];

        if (! is_array($conditions) || $conditions === []) {
            throw ValidationException::withMessages(['filter' => 'A filter group requires at least one condition.']);
        }

        if (! array_is_list($conditions)) {
            throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
        }

        $parsedConditions = [];

        foreach ($conditions as $condition) {
            if (is_array($condition) && array_key_exists('conditions', $condition)) {
                throw ValidationException::withMessages(['filter' => 'Nested filter groups are not supported.']);
            }

            if (! is_array($condition)
                || ! isset($condition['key']) || ! is_string($condition['key'])
                || ! isset($condition['op']) || ! is_string($condition['op'])) {
                throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
            }

            $parsedConditions[] = [
                'key' => $condition['key'],
                'filter' => [
                    'operator' => $condition['op'],
                    'value' => $this->getFilterValue($condition['value'] ?? null),
                ],
            ];
        }

        return new FilterGroup($groupOperator, $parsedConditions);
    }

    /**
     * A key that appears more than once collapses into a list of filter values,
     * which the filters apply in turn.
     *
     * @param  Collection<string, mixed>  $filters
     * @param  array{operator: string, value: mixed}  $filterValue
     */
    private function mergeFilterValue(Collection $filters, string $key, array $filterValue): void
    {
        if (! $filters->has($key)) {
            $filters->put($key, $filterValue);

            return;
        }

        $existing = $filters->get($key);

        $filters->put($key, isset($existing[0])
            ? array_merge($existing, [$filterValue])
            : [$existing, $filterValue]);
    }

    public function sorts(): Collection
    {
        $sortParameterName = config('query-builder.parameters.sort', 'sort');

        $sortParts = $this->getRequestData($sortParameterName);
        if (! empty($sortParts)) {
            if (is_string($sortParts)) {
                $sortParts = explode(static::getSortsArrayValueDelimiter(), $sortParts);
            }
        }
        if (empty($sortParts)) {
            $sortParts = $this->collect('order')
                ->map(fn (array $o) => $o['dir'] === 'asc' ? $o['field'] : '-'.$o['field'])
                ->toArray();
        }

        if (is_string($sortParts)) {
            $sortParts = explode(static::getSortsArrayValueDelimiter(), $sortParts);
        }

        return collect($sortParts)->filter();
    }
}
