<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\LineItemResource;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

class LineItemController
{
    #[ListEndpoint(
        path: '/api/v1/invoices/{id}/line-items',
        resource: LineItemResource::class,
        description: 'List line items for an invoice',
        parameters: [
            new FilterParameter([
                new IdFilter,
                new StringFilter(name: 'product_name'),
                new StringFilter(name: 'description'),
            ]),
        ],
    )]
    public function index(int $invoiceId): ResourceCollection
    {
        // Ensure invoice exists
        Invoice::query()->findOrFail($invoiceId);

        return LineItemResource::collection(
            QueryBuilder::for(LineItem::class)
                ->where('invoice_id', $invoiceId)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('product_name'),
                    QueryFilter::string('description'),
                    QueryFilter::number('quantity'),
                    QueryFilter::number('unit_price'),
                    QueryFilter::number('total_price'),
                    QueryFilter::number('discount_percent'),
                )
                ->apiPaginate()
        );
    }
}
