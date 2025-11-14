<?php declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Workbench\App\Enum\InvoiceStatusEnum;

#[OA\Schema(
    schema: 'UpdateInvoiceRequest',
    properties: [
        new OA\Property(property: 'customer_id', type: 'integer', description: 'ID of the customer'),
        new OA\Property(property: 'invoice_number', type: 'string', maxLength: 50),
        new OA\Property(property: 'status', enum: InvoiceStatusEnum::class),
        new OA\Property(property: 'total_amount', type: 'number', format: 'decimal', minimum: 0),
        new OA\Property(property: 'issued_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'due_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
    additionalProperties: false,
)]
class UpdateInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $invoiceId = $this->route('id');

        return [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'invoice_number' => ['sometimes', 'string', 'max:50', Rule::unique('invoices')->ignore($invoiceId)],
            'status' => ['sometimes', Rule::enum(InvoiceStatusEnum::class)],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after:issued_at'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
