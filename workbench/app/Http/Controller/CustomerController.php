<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\CustomerResource;
use Workbench\App\Models\Customer;
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

class CustomerController
{
    #[ListEndpoint(
        path: '/api/v1/customers',
        resource: CustomerResource::class,
        description: 'List customers',
        includes: ['invoices'],
        parameters: [
            new FilterParameter([
                new IdFilter,
                new StringFilter(name: 'name'),
                new StringFilter(name: 'email'),
                new StringFilter(name: 'country'),
            ]),
        ],
    )]
    public function index(): ResourceCollection
    {
        return CustomerResource::collection(
            QueryBuilder::for(Customer::class)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('name'),
                    QueryFilter::string('email'),
                    QueryFilter::string('country'),
                    QueryFilter::boolean('is_active'),
                )
                ->allowedIncludes(['invoices'])
                ->apiPaginate()
        );
    }

    #[GetEndpoint(
        path: '/api/v1/customers/{id}',
        resource: CustomerResource::class,
        description: 'Get customer',
        includes: ['invoices'],
    )]
    public function show(int $id): CustomerResource
    {
        $customer = QueryBuilder::for(Customer::class)
            ->where('id', $id)
            ->allowedIncludes(['invoices'])
            ->firstOrFail();

        return new CustomerResource($customer);
    }
}
