<?php

namespace App\Http\Resources\Api\V1\Dispatch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'description' => $this->description,
            'is_available' => $this->is_available,
            'is_active' => $this->is_active,
            'branches' => \App\Http\Resources\Api\V1\BranchResource::collection($this->whenLoaded('branches')),
        ];
    }
}
