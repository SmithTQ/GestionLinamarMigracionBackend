<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'campaigns' => CampaignResource::collection($this->whenLoaded('campaigns')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'created_at_iso' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
            'updated_at_iso' => $this->updated_at?->toISOString(),
        ];
    }
}
