<?php

namespace App\Http\Requests\Api\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourierInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp_number')) {
            $this->merge(['whatsapp_number' => preg_replace('/\D+/', '', (string) $this->input('whatsapp_number'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'whatsapp_number' => ['required', 'digits_between:8,15'],
            'expires_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays(30)->toDateTimeString()],
        ];
    }
}
