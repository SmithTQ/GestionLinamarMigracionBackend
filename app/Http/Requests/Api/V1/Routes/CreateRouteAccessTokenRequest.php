<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class CreateRouteAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['expires_at' => ['sometimes', 'date', 'after:now', 'before_or_equal:'.now()->addDays(30)->toDateTimeString()]];
    }
}
