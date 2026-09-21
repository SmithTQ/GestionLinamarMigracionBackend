<?php

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['form_id' => ['required', 'integer', 'exists:campaign_forms,id'], 'full_name' => ['sometimes', 'nullable', 'string', 'max:250'], 'whatsapp_number' => ['required', 'string', 'max:25'], 'email' => ['nullable', 'email', 'max:180'], 'expires_at' => ['nullable', 'date', 'after:now']];
    }
}
