<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class QueryBuilderRequest extends \Spatie\QueryBuilder\QueryBuilderRequest
{
    public function filters(): Collection
    {
        $filterParameterName = config('query-builder.parameters.filter', 'filter');

        $filterParts = $this->getRequestData($filterParameterName, []);

        if (is_string($filterParts)) {
            // If the filter is a JSON string, decode it. This is needed to support SwaggerUI properly
            try {
                $filterParts = json_decode($filterParts, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return collect();
            }

            if (isset($filterParts['key'])) {
                // If the filter is a single filter object, convert it to an array
                $filterParts = [$filterParts];
            }
        }

        try {
            $filters = collect();

            foreach ($filterParts as $filter) {
                $key = $filter['key'];
                $filterValue = [
                    'operator' => $filter['op'],
                    'value' => $this->getFilterValue($filter['value']),
                ];

                // If the key already exists, make it an array of filters
                if ($filters->has($key)) {
                    $existing = $filters->get($key);
                    // If it's not already an array of arrays, convert it
                    if (! isset($existing[0])) {
                        $filters->put($key, [$existing, $filterValue]);
                    } else {
                        $filters->put($key, array_merge($existing, [$filterValue]));
                    }
                } else {
                    $filters->put($key, $filterValue);
                }
            }

            return $filters;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['filter' => 'Invalid filter format.']);
        }
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
