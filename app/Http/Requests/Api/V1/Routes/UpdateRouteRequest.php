<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_distance_km' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
