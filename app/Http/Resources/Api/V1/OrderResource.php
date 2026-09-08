<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'delivery_date' => $this->delivery_date?->toDateString(),
            'delivery_time' => $this->delivery_time?->format('H:i'),
            'status' => $this->status,
            'campaign' => new CampaignResource($this->whenLoaded('campaign')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'district_catalog' => new DistrictResource($this->whenLoaded('districtCatalog')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
