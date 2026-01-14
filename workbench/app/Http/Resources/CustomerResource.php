<?php declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Workbench\App\Models\Customer;
use Xentral\LaravelApi\Http\ApiResource;

#[OA\Schema(
    schema: 'CustomerResource',
    required: ['id', 'name', 'email', 'country', 'is_active', 'is_verified', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Acme Corporation'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'contact@acme.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+1-555-0123'),
        new OA\Property(property: 'country', type: 'string', example: 'US'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'is_verified', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
        new OA\Property(
            property: 'invoices',
            type: 'array',
            items: new OA\Items(ref: InvoiceResource::class),
        ),
    ],
    type: 'object',
    additionalProperties: false,
)]
class CustomerResource extends ApiResource
{
    use IsMetaFieldResource;

    /** @var Customer */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'country' => $this->resource->country,
            'is_active' => $this->resource->is_active,
            'is_verified' => (bool) $this->resource->is_verified,
            'created_at' => $this->resource->created_at->toAtomString(),
            'updated_at' => $this->resource->updated_at->toAtomString(),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            ...$this->mergeAdditionalFields(),
        ];
    }

    private array $additionalFields = [];

    protected function addAdditionalFields(array $fields): void
    {
        $this->additionalFields[] = $fields;
    }

    protected function mergeAdditionalFields(): array
    {
        return array_map(fn ($ref) => $this->merge($ref), $this->additionalFields);
    }
}
