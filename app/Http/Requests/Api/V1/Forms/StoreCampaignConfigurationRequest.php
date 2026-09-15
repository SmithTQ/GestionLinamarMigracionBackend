<?php

namespace App\Http\Requests\Api\V1\Forms;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form' => ['required', 'array'],
            'form.template_id' => ['required', 'integer', 'exists:form_templates,id'],
            'form.branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'form.title' => ['required', 'string', 'max:180'],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.fields' => ['sometimes', 'array'],
            'form.fields.*.field_id' => ['required', 'integer', 'distinct', 'exists:form_fields,id'],
            'form.fields.*.is_enabled' => ['sometimes', 'boolean'],
            'form.fields.*.is_required' => ['sometimes', 'boolean'],
            'form.fields.*.label' => ['nullable', 'string', 'max:180'],
            'form.fields.*.config' => ['nullable', 'array'],
            'form.fields.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'products.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'products.*.is_available' => ['sometimes', 'boolean'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'products.*.max_quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
