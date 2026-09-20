<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Http\Requests\NormalizesDeliveryDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    use NormalizesDeliveryDate;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDeliveryDateForValidation();
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'external_source' => ['nullable', 'string', 'max:60', 'required_with:external_key'],
            'external_key' => ['nullable', 'string', 'max:150', 'required_with:external_source'],
            'order_number' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['required_without:product_id', 'nullable', 'string', 'max:150'],
            'product_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sender_name' => ['required', 'string', 'max:250'],
            'sender_phone' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:250'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'district' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'delivery_reference' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dedication' => ['nullable', 'string', 'max:5000'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_time' => ['required', 'string', 'max:255'],
        ];
    }
}
