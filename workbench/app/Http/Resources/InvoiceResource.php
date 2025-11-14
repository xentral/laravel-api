<?php declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Workbench\App\Enum\InvoiceStatusEnum;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Http\ApiResource;

#[OA\Schema(
    schema: 'InvoiceResource',
    required: ['id', 'invoice_number', 'customer_id', 'status', 'total_amount', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'INV-2024-001'),
        new OA\Property(property: 'customer_id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: InvoiceStatusEnum::class, example: 'sent'),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 1500.00),
        new OA\Property(property: 'issued_at', type: 'string', format: 'date-time',
            example: '2024-01-15T10:00:00+00:00', nullable: true),
        new OA\Property(property: 'due_at', type: 'string', format: 'date-time', example: '2024-02-15T10:00:00+00:00',
            nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', example: '2024-02-10T14:30:00+00:00',
            nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time',
            example: '2024-01-15T10:00:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time',
            example: '2024-01-15T10:00:00+00:00'),
        new OA\Property(
            property: 'customer',
            ref: CustomerResource::class,
            nullable: true
        ),
        new OA\Property(
            property: 'line_items',
            type: 'array',
            items: new OA\Items(ref: LineItemResource::class),
        ),
    ],
    type: 'object',
    additionalProperties: false,
)]
class InvoiceResource extends ApiResource
{
    /** @var Invoice */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'invoice_number' => $this->resource->invoice_number,
            'customer_id' => $this->resource->customer_id,
            'status' => $this->resource->status,
            'total_amount' => $this->resource->total_amount,
            'issued_at' => $this->resource->issued_at?->toAtomString(),
            'due_at' => $this->resource->due_at?->toAtomString(),
            'paid_at' => $this->resource->paid_at?->toAtomString(),
            'created_at' => $this->resource->created_at->toAtomString(),
            'updated_at' => $this->resource->updated_at->toAtomString(),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'line_items' => LineItemResource::collection($this->whenLoaded('lineItems')),
        ];
    }
}
