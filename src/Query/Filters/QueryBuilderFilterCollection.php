<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

interface QueryBuilderFilterCollection
{
    public function getFilters(): array;
}
