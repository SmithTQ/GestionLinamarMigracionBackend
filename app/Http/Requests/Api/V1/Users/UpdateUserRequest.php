<?php

namespace App\Http\Requests\Api\V1\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($this->route('user'))],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'campaign_ids' => ['sometimes', 'array'],
            'campaign_ids.*' => ['integer', 'distinct', 'exists:campaigns,id'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
        ];
    }
}
