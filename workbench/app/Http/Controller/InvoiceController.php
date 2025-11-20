<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Enum\InvoiceStatusEnum;
use Workbench\App\Http\Requests\CreateInvoiceRequest;
use Workbench\App\Http\Resources\InvoiceResource;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\PostEndpoint;
use Xentral\LaravelApi\OpenApi\Filters\DateFilter;
use Xentral\LaravelApi\OpenApi\Filters\EnumFilter;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\DummyInclude;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

class InvoiceController
{
    #[ListEndpoint(
        path: '/api/v1/invoices',
        resource: InvoiceResource::class,
        description: 'List invoices',
        includes: ['customer', 'lineItems'],
        parameters: [
            new FilterParameter([
                new IdFilter,
                new StringFilter(name: 'invoice_number'),
                new EnumFilter(name: 'status', enumSource: InvoiceStatusEnum::class),
                new DateFilter(name: 'issued_at'),
                new DateFilter(name: 'due_at'),
                new DateFilter(name: 'paid_at'),
                new DateFilter(name: 'created_at'),
                new DateFilter(name: 'updated_at'),
            ]),
        ],
        paginationType: [PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR],
    )]
    public function index(): ResourceCollection
    {
        return InvoiceResource::collection(
            QueryBuilder::for(Invoice::class)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('invoice_number'),
                    QueryFilter::string('status'),
                    QueryFilter::number('total_amount'),
                    QueryFilter::date('issued_at'),
                    QueryFilter::date('due_at'),
                    QueryFilter::date('paid_at'),
                    QueryFilter::date('created_at'),
                    QueryFilter::date('updated_at'),
                    QueryFilter::identifier('customer_id'),
                    QueryFilter::string('customer.name', 'customer.name'),
                    QueryFilter::string('customer.email', 'customer.email'),
                    QueryFilter::string('customer.phone', 'customer.phone'),
                    QueryFilter::string('customer.country', 'customer.country'),
                    QueryFilter::boolean('customer.is_active', 'customer.is_active'),
                    QueryFilter::string('lineItems.product_name', 'lineItems.product_name'),
                    QueryFilter::number('lineItems.quantity', 'lineItems.quantity'),
                    QueryFilter::number('lineItems.unit_price', 'lineItems.unit_price'),
                    QueryFilter::number('lineItems.total_price', 'lineItems.total_price'),
                )
                ->allowedIncludes(['customer', 'lineItems', DummyInclude::make('lineItems.customFields')])
                ->apiPaginate(100, PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR)
        );
    }

    #[GetEndpoint(
        path: '/api/v1/invoices/{id}',
        resource: InvoiceResource::class,
        description: 'Get invoice',
        includes: ['customer', 'lineItems'],
    )]
    public function show(int $id): InvoiceResource
    {
        $invoice = QueryBuilder::for(Invoice::class)
            ->where('id', $id)
            ->allowedIncludes(['customer', 'lineItems', DummyInclude::make('lineItems.customFields')])
            ->firstOrFail();

        return new InvoiceResource($invoice);
    }

    #[PostEndpoint(
        path: '/api/v1/invoices',
        resource: InvoiceResource::class,
        description: 'Create invoice',
        request: CreateInvoiceRequest::class,
    )]
    public function create(CreateInvoiceRequest $request): InvoiceResource
    {
        $invoice = Invoice::create($request->validated());

        return new InvoiceResource($invoice);
    }
}
