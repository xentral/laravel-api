<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\InvoiceResource;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

class ListInvoicesController
{
    public function __invoke(): ResourceCollection
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
                ->allowedIncludes(['customer', 'lineItems'])
                ->apiPaginate()
        );
    }
}
