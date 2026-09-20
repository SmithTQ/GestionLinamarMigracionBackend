<?php

namespace App\Http\Requests\Api\V1\Couriers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'unique:couriers,phone'],
            'description' => ['nullable', 'string', 'max:250'],
            'is_available' => ['sometimes', 'boolean'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
        ];
    }
}
