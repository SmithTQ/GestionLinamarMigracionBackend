<?php

namespace App\Http\Requests\Api\V1\Districts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:20', 'alpha_dash', Rule::unique('districts', 'code')->ignore($this->route('district'))],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'macroregion' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
