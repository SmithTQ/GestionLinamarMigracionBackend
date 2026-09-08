<?php

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(['pending', 'validated', 'planned', 'assigned', 'in_transit', 'delivered', 'failed', 'cancelled'])]];
    }
}
