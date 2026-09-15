<?php

namespace App\Http\Requests\Api\V1\Forms;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['campaign_id' => ['required', 'integer', 'exists:campaigns,id'], 'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'], 'template_id' => ['required', 'integer', 'exists:form_templates,id'], 'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'], 'fields' => ['sometimes', 'array'], 'fields.*.field_id' => ['required', 'integer', 'distinct', 'exists:form_fields,id'], 'fields.*.is_enabled' => ['sometimes', 'boolean'], 'fields.*.is_required' => ['sometimes', 'boolean'], 'fields.*.label' => ['nullable', 'string', 'max:180'], 'fields.*.config' => ['nullable', 'array'], 'fields.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535']];
    }
}
