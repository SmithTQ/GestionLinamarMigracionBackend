<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\FormInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormInvitation */
class CustomerInvitationResource extends JsonResource
{
    use FormatsResourceDates;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->campaign_form_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'expires_at' => $this->formatResourceDate($this->expires_at, 'd/m/Y H:i'),
            'expires_at_iso' => $this->formatResourceDateIso($this->expires_at),
            'used_at' => $this->formatResourceDate($this->used_at, 'd/m/Y H:i'),
            'used_at_iso' => $this->formatResourceDateIso($this->used_at),
            'revoked_at' => $this->formatResourceDate($this->revoked_at, 'd/m/Y H:i'),
            'revoked_at_iso' => $this->formatResourceDateIso($this->revoked_at),
            'created_at' => $this->formatResourceDate($this->created_at, 'd/m/Y H:i'),
            'created_at_iso' => $this->formatResourceDateIso($this->created_at),
            'updated_at' => $this->formatResourceDate($this->updated_at, 'd/m/Y H:i'),
            'updated_at_iso' => $this->formatResourceDateIso($this->updated_at),
        ];
    }
}
