<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'stops' => ['required', 'array', 'min:1'],
            'stops.*.order_id' => ['required', 'integer', 'distinct', 'exists:orders,id'],
            'stops.*.sort_order' => ['required', 'integer', 'min:1'],
            'estimated_distance_km' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'max:100', 'alpha_dash'],
        ];
    }
}
