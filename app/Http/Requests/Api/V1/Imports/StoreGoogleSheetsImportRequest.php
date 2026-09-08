<?php

namespace App\Http\Requests\Api\V1\Imports;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoogleSheetsImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'spreadsheet_id' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'range' => ['required', 'string', 'max:255'],
        ];
    }
}
