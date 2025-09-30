<?php declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Xentral\LaravelApi\Http\ApiResource;

class InvoiceResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'line_items' => LineItemResource::collection($this->whenLoaded('lineItems')),
        ];
    }
}
