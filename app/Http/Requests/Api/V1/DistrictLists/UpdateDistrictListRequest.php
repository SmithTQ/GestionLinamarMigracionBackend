<?php

namespace App\Http\Requests\Api\V1\DistrictLists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
            'code' => ['sometimes', 'required', 'string', 'max:60', 'alpha_dash', Rule::unique('district_lists', 'code')->ignore($this->route('districtList'))],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'district_ids' => ['sometimes', 'array', 'min:1'],
            'district_ids.*' => ['required', 'integer', 'distinct', 'exists:districts,id'],
        ];
    }
}
