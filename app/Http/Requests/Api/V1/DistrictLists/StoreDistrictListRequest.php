<?php

namespace App\Http\Requests\Api\V1\DistrictLists;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistrictListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:district_lists,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'district_ids' => ['required', 'array', 'min:1'],
            'district_ids.*' => ['required', 'integer', 'distinct', 'exists:districts,id'],
        ];
    }
}
