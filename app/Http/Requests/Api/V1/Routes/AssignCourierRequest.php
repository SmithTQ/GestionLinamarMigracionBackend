<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['courier_id' => ['required', 'integer', 'exists:couriers,id']];
    }
}
