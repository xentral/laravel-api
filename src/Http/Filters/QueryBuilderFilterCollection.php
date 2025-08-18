<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Http\Filters;

interface QueryBuilderFilterCollection
{
    public function getFilters(): array;
}
