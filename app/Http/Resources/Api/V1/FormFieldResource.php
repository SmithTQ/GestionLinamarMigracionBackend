<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormField */
class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'label' => $this->label, 'description' => $this->description, 'type' => $this->type, 'field_group' => $this->field_group, 'is_system' => $this->is_system, 'is_active' => $this->is_active, 'validation_rules' => $this->validation_rules, 'sort_order' => $this->sort_order, 'form_config' => $this->whenPivotLoaded('campaign_form_fields', fn () => ['is_enabled' => $this->pivot->is_enabled, 'is_required' => $this->pivot->is_required, 'label' => $this->pivot->label, 'config' => $this->pivot->config, 'sort_order' => $this->pivot->sort_order])];
    }
}
