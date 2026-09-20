<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class AttachOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'distinct', 'exists:orders,id'],
        ];
    }
}
