<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_distance_km' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
