<?php

namespace App\Http\Requests\Api\V1\Forms;

use App\Http\Requests\NormalizesDeliveryDate;
use App\Models\CampaignForm;
use App\Models\FormInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PublicFormSubmissionRequest extends FormRequest
{
    use NormalizesDeliveryDate;

    private const RESERVED_KEYS = [
        'submission_key', 'product_sku', 'sender_name', 'sender_phone',
        'recipient_name', 'recipient_phone', 'district_code', 'address',
        'latitude', 'longitude', 'location_accuracy', 'dedication',
        'delivery_date', 'delivery_time', 'photo', 'adicional',
    ];

    private const FIXED_FIELD_KEYS = ['address', 'delivery_date', 'delivery_time', 'dedication', 'photo'];

    private ?Collection $publicFields = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDeliveryDateForValidation();
    }

    public function rules(): array
    {
        $rules = [
            'submission_key' => ['required', 'string', 'max:100', 'alpha_dash'],
            'product_sku' => ['required', 'string', 'max:60'],
            'sender_name' => ['required', 'string', 'max:250'],
            'sender_phone' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:250'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'district_code' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dedication' => ['nullable', 'string', 'max:5000'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($this->publicFields() as $field) {
            if ($field->field_group === 'required_base' || in_array($field->key, self::FIXED_FIELD_KEYS, true)) {
                continue;
            }

            $fieldRules = $field->pivot->is_required ? ['required'] : ['sometimes', 'nullable'];
            $rules[$field->key] = [...$fieldRules, ...$this->rulesForField($field)];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $allowed = array_merge(array_keys($this->rules()), self::RESERVED_KEYS);
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no pertenece al formulario publicado.');
                }
            }

            foreach ($this->publicFields()->where('is_system', false) as $field) {
                if (! in_array($field->key, self::FIXED_FIELD_KEYS, true) && in_array($field->key, self::RESERVED_KEYS, true)) {
                    $validator->errors()->add($field->key, 'La clave del campo esta reservada por el sistema.');
                }
            }
        });
    }

    private function rulesForField($field): array
    {
        $validationRules = is_array($field->validation_rules) ? $field->validation_rules : [];
        $config = $this->pivotConfig($field);
        $maxLength = $config['max_length'] ?? $validationRules['max_length'] ?? null;
        $rules = match ($field->type) {
            'text', 'textarea', 'phone' => ['string'],
            'number' => ['numeric'],
            'date' => ['date'],
            'time' => ['date_format:H:i'],
            'file' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($config['max_size_kb'] ?? $validationRules['max_size_kb'] ?? config('forms.file_max_size_kb', 5120))],
            'select' => [Rule::in(array_values($config['options'] ?? []))],
            default => [],
        };

        if ($maxLength !== null && in_array($field->type, ['text', 'textarea', 'phone'], true)) {
            $rules[] = 'max:'.$maxLength;
        }

        return $rules;
    }

    private function pivotConfig($field): array
    {
        $config = $field->pivot->config ?? null;
        if (is_array($config)) {
            return $config;
        }

        $decoded = is_string($config) ? json_decode($config, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function publicFields(): Collection
    {
        if ($this->publicFields !== null) {
            return $this->publicFields;
        }

        $form = null;
        $publicKey = $this->route('publicKey');
        $token = $this->route('token');
        if ($publicKey) {
            $form = CampaignForm::with('fields')->where('public_key', $publicKey)->first();
        } elseif ($token) {
            $form = FormInvitation::with('form.fields')->where('token', $token)->first()?->form;
        }

        return $this->publicFields = $form?->fields
            ?->filter(fn ($field) => $field->is_active && (bool) $field->pivot->is_enabled)
            ?? collect();
    }
}
