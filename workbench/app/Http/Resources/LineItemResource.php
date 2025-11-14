<?php declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Http\ApiResource;

#[OA\Schema(
    schema: 'LineItemResource',
    required: ['id', 'invoice_id', 'product_name', 'quantity', 'unit_price', 'total_price', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_id', type: 'integer', example: 1),
        new OA\Property(property: 'product_name', type: 'string', example: 'Premium Widget'),
        new OA\Property(property: 'description', type: 'string', example: 'High-quality widget with extended warranty', nullable: true),
        new OA\Property(property: 'quantity', type: 'integer', example: 10),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 500.00),
        new OA\Property(property: 'discount_percent', type: 'number', format: 'float', example: 10.00, nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-15T10:00:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-15T10:00:00+00:00'),
    ],
    type: 'object',
    additionalProperties: false,
)]
class LineItemResource extends ApiResource
{
    /** @var LineItem */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'invoice_id' => $this->resource->invoice_id,
            'product_name' => $this->resource->product_name,
            'description' => $this->resource->description,
            'quantity' => $this->resource->quantity,
            'unit_price' => $this->resource->unit_price,
            'total_price' => $this->resource->total_price,
            'discount_percent' => $this->resource->discount_percent,
            'created_at' => $this->resource->created_at->toAtomString(),
            'updated_at' => $this->resource->updated_at->toAtomString(),
        ];
    }
}
