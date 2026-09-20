<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRouteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(['draft', 'planned', 'assigned', 'dispatched', 'completed', 'cancelled'])]];
    }
}
