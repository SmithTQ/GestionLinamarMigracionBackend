<?php

namespace App\Http\Requests\Api\V1\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/'],
            'label' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'phone', 'select', 'file', 'product', 'district', 'map', 'time'])],
            'is_system' => ['sometimes', Rule::in([false, 0, '0'])],
            'validation_rules' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
