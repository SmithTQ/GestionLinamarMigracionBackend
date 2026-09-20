<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\FormSubmission;
use App\Models\Order;
use App\Models\OrderDeliveryEvidence;
use Carbon\CarbonImmutable;
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
            'delivery_reference' => $this->delivery_reference,
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
            'delivery_evidence' => $this->deliveryEvidenceData(),
            'route_stop' => $this->whenPivotLoaded('route_order', function (): array {
                /** @phpstan-ignore-next-line Pivot is attached by the route_order relationship. */
                $pivot = $this->pivot;

                return [
                    'sort_order' => $pivot->sort_order ?? $pivot->position,
                    'assigned_at' => $this->formatResourceDateIso($pivot->assigned_at),
                    'is_active' => (bool) $pivot->is_active,
                ];
            }),
            'active_route' => $this->when($this->relationLoaded('activeRoutes'), function (): ?array {
                /** @var DeliveryRoute|null $route */
                $route = $this->activeRoutes->first();

                return $route === null ? null : [
                    'id' => $route->id,
                    'code' => $route->code,
                    'name' => $route->name,
                    'status' => $route->status,
                    'courier_id' => $route->courier_id,
                ];
            }),
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

    private function deliveryEvidenceData(): ?array
    {
        if (! $this->relationLoaded('deliveryEvidences')) {
            return null;
        }

        $evidence = $this->deliveryEvidences
            ->sortByDesc(fn ($item): string => (string) $item->getAttribute('delivered_at'))
            ->first();
        if (! $evidence instanceof OrderDeliveryEvidence) {
            return null;
        }

        $deliveredAtValue = $evidence->getAttribute('delivered_at');
        $deliveredAt = $deliveredAtValue === null ? null : CarbonImmutable::parse((string) $deliveredAtValue);
        /** @var Courier|null $courier */
        $courier = $evidence->courier;

        return [
            'id' => $evidence->id,
            'delivered_at' => $deliveredAt === null ? null : $deliveredAt->format('d/m/Y H:i'),
            'delivered_at_iso' => $deliveredAt === null ? null : $deliveredAt->toISOString(),
            'delivered_by_courier_id' => $evidence->delivered_by_courier_id,
            'delivered_by_courier_name' => $courier?->name,
        ];
    }
}
