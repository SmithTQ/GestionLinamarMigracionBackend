<?php

namespace App\Http\Requests\Api\V1\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/'],
            'label' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => ['sometimes', Rule::in(['text', 'textarea', 'number', 'date', 'phone', 'select', 'file', 'product', 'district', 'map', 'time'])],
            'validation_rules' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
