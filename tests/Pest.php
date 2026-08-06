<?php declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;
use Xentral\LaravelApi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');
uses(TestCase::class)->in(__DIR__.'/Unit');

function buildFilterQuery(array $filters): string
{
    $normalizedFilters = array_map(function ($filter) {
        if (isset($filter['value']) && ! is_bool($filter['value']) && ! is_string($filter['value']) && ! is_array($filter['value'])) {
            $filter['value'] = (string) $filter['value'];
        }

        return $filter;
    }, $filters);

    return http_build_query(['filter' => json_encode($normalizedFilters)]);
}

function workbench_dir(): string
{
    return dirname(__DIR__).'/workbench';
}

/**
 * Reads the documented meta keys for one pagination type out of the generated
 * OpenAPI spec, so runtime assertions cannot drift from the schemas in
 * PaginationResponseProcessor. Cached per casing, the only config that changes
 * the generated key names.
 *
 * @return list<string>
 */
function documentedPaginationMetaKeys(string $paginationType): array
{
    static $cache = [];

    $casing = config('openapi.schemas.default.config.pagination_response.casing', 'snake');

    if (! isset($cache[$casing])) {
        $generator = (new OpenApiGeneratorFactory)->create(config('openapi.schemas.default'));
        $spec = Yaml::parse($generator->generate([workbench_dir()])->toYaml());
        $schema = $spec['paths']['/api/v1/invoices']['get']['responses'][200]['content']['application/json']['schema'];

        foreach ($schema['anyOf'] ?? [] as $branch) {
            $type = Str::after($branch['title'] ?? '', 'Paginated Response: ');
            $cache[$casing][$type] = array_keys($branch['properties']['meta']['properties'] ?? []);
        }
    }

    $keys = $cache[$casing][$paginationType] ?? [];

    if ($keys === []) {
        throw new RuntimeException(
            "The generated spec documents no meta keys for '{$paginationType}' pagination. "
            .'Either the pagination type is gone or the response schema shape changed; '
            .'fix this helper rather than letting the assertion pass vacuously.'
        );
    }

    return $keys;
}
