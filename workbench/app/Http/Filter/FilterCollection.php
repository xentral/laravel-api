<?php declare(strict_types=1);

namespace Workbench\App\Http\Filter;

use Xentral\LaravelApi\Attributes\FilterProperty;
use Xentral\LaravelApi\Attributes\FilterSpecCollection;
use Xentral\LaravelApi\Http\Filters\QueryBuilderFilterCollection;
use Xentral\LaravelApi\Http\Filters\QueryFilter;

class FilterCollection implements FilterSpecCollection, QueryBuilderFilterCollection
{
    public function getFilterSpecification(): array
    {
        return [
            new FilterProperty(name: 'created_at', type: 'date-time'),
            new FilterProperty(name: 'updated_at', type: 'date-time'),
        ];
    }

    public function getFilters(): array
    {
        return [
            QueryFilter::date('updated_at'),
            QueryFilter::date('created_at'),
        ];
    }
}
