<?php declare(strict_types=1);

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
