<?php

namespace App\Http\Resources\Api\V1\Dispatch;

use App\Http\Resources\Api\V1\BranchResource;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\DeliveryRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryRoute */
class DeliveryRouteResource extends JsonResource
{
    use FormatsResourceDates;

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
            'dispatched_at' => $this->formatResourceDate($this->dispatched_at, 'd/m/Y H:i'),
            'dispatched_at_iso' => $this->formatResourceDateIso($this->dispatched_at),
            'campaign' => new CampaignResource($this->whenLoaded('campaign')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'courier' => new CourierResource($this->whenLoaded('courier')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
