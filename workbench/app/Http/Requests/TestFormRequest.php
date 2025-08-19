<?php declare(strict_types=1);
namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => [Rule::exists('categories', 'id')],
            'age' => ['required', 'integer', 'min:18'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
