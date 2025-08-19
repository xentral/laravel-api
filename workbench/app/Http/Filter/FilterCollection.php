<?php declare(strict_types=1);
namespace Workbench\App\Http\Filter;

use Xentral\LaravelApi\OpenApi\Filters\FilterProperty;
use Xentral\LaravelApi\OpenApi\Filters\FilterSpecCollection;
use Xentral\LaravelApi\Query\Filters\QueryBuilderFilterCollection;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

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
