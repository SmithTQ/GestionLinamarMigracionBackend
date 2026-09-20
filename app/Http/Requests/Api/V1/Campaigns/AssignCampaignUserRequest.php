<?php

namespace App\Http\Requests\Api\V1\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class AssignCampaignUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
