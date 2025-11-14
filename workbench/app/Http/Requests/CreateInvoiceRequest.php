<?php declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Workbench\App\Enum\InvoiceStatusEnum;

#[OA\Schema(
    schema: 'CreateInvoiceRequest',
    required: ['customer_id', 'invoice_number', 'status', 'total_amount'],
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
class CreateInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_number' => ['required', 'string', 'unique:invoices', 'max:50'],
            'status' => ['required', Rule::enum(InvoiceStatusEnum::class)],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:issued_at'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
