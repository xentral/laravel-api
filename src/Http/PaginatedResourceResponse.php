<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\PaginatedResourceResponse as BasePaginatedResourceResponse;
use Illuminate\Support\Arr;

class PaginatedResourceResponse extends BasePaginatedResourceResponse
{
    protected function paginationLinks($paginated): array
    {
        $links = [
            'first' => $paginated['first_page_url'] ?? null,
            'last' => $paginated['last_page_url'] ?? null,
            'prev' => $paginated['prev_page_url'] ?? null,
            'next' => $paginated['next_page_url'] ?? null,
        ];

        return $this->convertCasing($links);
    }

    protected function meta($paginated): array
    {
        $meta = Arr::except($paginated, [
            'data',
            'first_page_url',
            'last_page_url',
            'prev_page_url',
            'next_page_url',
        ]);

        return $this->convertCasing($meta);
    }

    private function convertCasing(array $data): array
    {
        $casing = $this->getPaginationCasing();

        if ($casing === 'camel') {
            return $this->convertToCamelCase($data);
        }

        return $data;
    }

    private function getPaginationCasing(): string
    {
        try {
            return config('openapi.schemas.default.config.pagination_response.casing', 'snake');
        } catch (\Throwable) {
            return 'snake';
        }
    }

    private function convertToCamelCase(array $data): array
    {
        $converted = [];

        foreach ($data as $key => $value) {
            // Only convert string keys to camelCase, keep numeric keys as-is
            $camelKey = is_string($key) ? $this->toCamelCase($key) : $key;

            if (is_array($value)) {
                $converted[$camelKey] = $this->convertToCamelCase($value);
            } else {
                $converted[$camelKey] = $value;
            }
        }

        return $converted;
    }

    private function toCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
}
