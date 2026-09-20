<?php

namespace App\Http\Requests\Api\V1\Districts;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:districts,code'],
            'name' => ['required', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'macroregion' => ['nullable', 'string', 'max:80'],
        ];
    }
}
