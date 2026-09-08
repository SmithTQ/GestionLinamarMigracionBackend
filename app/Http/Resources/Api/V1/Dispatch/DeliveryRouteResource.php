<?php

namespace App\Http\Resources\Api\V1\Dispatch;

use App\Http\Resources\Api\V1\BranchResource;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'branch_id' => $this->branch_id,
            'courier_id' => $this->courier_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'total_distance_km' => $this->total_distance_km,
            'estimated_minutes' => $this->estimated_minutes,
            'status' => $this->status,
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'campaign' => new CampaignResource($this->whenLoaded('campaign')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'courier' => new CourierResource($this->whenLoaded('courier')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
