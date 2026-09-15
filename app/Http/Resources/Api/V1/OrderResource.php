<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\FormSubmission;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    use FormatsResourceDates;

    public function toArray(Request $request): array
    {
        $fieldData = $this->fieldData($request);

        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'district_id' => $this->district_id,
            'external_source' => $this->external_source,
            'external_key' => $this->external_key,
            'order_number' => $this->order_number,
            'product_name' => $this->product_name,
            'product_price' => $this->product_price,
            'sender_name' => $this->sender_name,
            'sender_phone' => $this->sender_phone,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'district' => $this->district,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_accuracy' => $this->location_accuracy,
            'dedication' => $this->dedication,
            'delivery_date' => $this->formatResourceDate($this->delivery_date, 'd/m/Y'),
            'delivery_date_iso' => $this->formatResourceDateOnly($this->delivery_date),
            'delivery_time' => $this->delivery_time,
            'optional_fields' => $fieldData['optional_fields'],
            'custom_fields' => $fieldData['custom_fields'],
            'field_indicators' => $fieldData['field_indicators'],
            'status' => $this->status,
            'campaign' => new CampaignResource($this->whenLoaded('campaign')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'district_catalog' => new DistrictResource($this->whenLoaded('districtCatalog')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_at' => $this->formatResourceDate($this->created_at, 'd/m/Y H:i'),
            'created_at_iso' => $this->formatResourceDateIso($this->created_at),
            'updated_at' => $this->formatResourceDate($this->updated_at, 'd/m/Y H:i'),
            'updated_at_iso' => $this->formatResourceDateIso($this->updated_at),
        ];
    }

    private function fieldData(Request $request): array
    {
        if (! $this->relationLoaded('formSubmission') || ! $this->formSubmission) {
            return ['optional_fields' => [], 'custom_fields' => [], 'field_indicators' => []];
        }

        /** @var FormSubmission $submission */
        $submission = $this->formSubmission;
        $fields = $submission->relationLoaded('form') && $submission->form?->relationLoaded('fields')
            ? $submission->form->fields
            : collect();
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $files = $submission->relationLoaded('files') ? $submission->files->keyBy('form_field_id') : collect();
        $optional = [];
        $custom = [];
        $indicators = [];
        $structured = ['address', 'delivery_date', 'delivery_time', 'dedication'];

        foreach ($fields->where('is_active', true)->where('pivot.is_enabled', true) as $field) {
            $key = $field->key;
            $label = $field->pivot->label ?: $field->label;
            $file = $files->get($field->id);
            $value = in_array($key, $structured, true) ? $this->{$key} : ($file ? ['id' => $file->id, 'name' => $file->original_name, 'mime_type' => $file->mime_type, 'size' => $file->size, 'url' => url('/api/v1/form-submission-files/'.$file->id)] : ($payload[$key] ?? null));
            $filled = $file !== null || ($value !== null && $value !== '');
            $item = ['key' => $key, 'label' => $label, 'type' => $field->type, 'value' => $value, 'filled' => $filled];

            if ($field->field_group === 'optional_base') {
                $optional[$key] = ['label' => $label, 'filled' => $filled, 'value' => $value];
            } elseif ($field->field_group === 'custom') {
                $custom[] = $item;
            }

            if ($filled) {
                $indicators[] = ['key' => $key, 'label' => $label, 'filled' => true];
            }
        }

        return ['optional_fields' => $optional, 'custom_fields' => $custom, 'field_indicators' => $indicators];
    }
}
