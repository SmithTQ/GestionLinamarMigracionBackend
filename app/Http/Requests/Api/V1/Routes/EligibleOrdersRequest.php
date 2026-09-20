<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class EligibleOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:id,order_number,recipient_name,delivery_date,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
