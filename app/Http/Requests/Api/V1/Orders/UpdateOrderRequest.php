<?php

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'recipient_name' => ['sometimes', 'required', 'string', 'max:250'],
            'recipient_phone' => ['sometimes', 'required', 'string', 'max:30'],
            'district' => ['sometimes', 'required', 'string', 'max:120'],
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dedication' => ['nullable', 'string', 'max:5000'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
