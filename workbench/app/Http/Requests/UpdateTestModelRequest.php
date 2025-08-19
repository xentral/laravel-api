<?php declare(strict_types=1);
namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Workbench\App\Enum\StatusEnum;

#[OA\Schema(
    schema: 'UpdateTestModelRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: StatusEnum::class, nullable: true),
    ],
    type: 'object',
    additionalProperties: false,
)]
class UpdateTestModelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable', 'string',
            'status' => 'nullable', Rule::enum(StatusEnum::class),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
