<?php

namespace App\Http\Requests\Api\V1\Campaigns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:40', 'alpha_dash', Rule::unique('campaigns', 'code')->ignore($this->route('campaign'))],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['draft', 'open', 'closed', 'cancelled'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
            'district_list_ids' => ['sometimes', 'array'],
            'district_list_ids.*' => ['integer', 'distinct', 'exists:district_lists,id'],
        ];
    }
}
