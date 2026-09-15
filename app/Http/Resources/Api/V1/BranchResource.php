<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'campaigns' => CampaignResource::collection($this->whenLoaded('campaigns')),
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'created_at_iso' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
            'updated_at_iso' => $this->updated_at?->toISOString(),
        ];
    }
}
