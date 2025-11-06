<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Query\QueryBuilder;
use Xentral\LaravelApi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

function createQueryFromFilterRequest(array $filters, ?string $model = null): QueryBuilder
{
    $model ??= TestModel::class;

    $request = new Request([
        'filter' => $filters,
    ]);

    return QueryBuilder::for($model, $request);
}

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
