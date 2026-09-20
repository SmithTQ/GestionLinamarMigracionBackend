<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DistrictList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DistrictList */
class DistrictListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'district_count' => $this->when(isset($this->district_count), $this->district_count),
            'districts' => DistrictResource::collection($this->whenLoaded('districts')),
        ];
    }
}
