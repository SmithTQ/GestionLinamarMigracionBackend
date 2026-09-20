<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteStopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stops' => ['required', 'array', 'min:1'],
            'stops.*.order_id' => ['required', 'integer', 'distinct', 'exists:orders,id'],
            'stops.*.sort_order' => ['required', 'integer', 'min:1'],
            'estimated_distance_km' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
