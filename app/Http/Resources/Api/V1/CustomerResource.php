<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'full_name' => $this->full_name, 'whatsapp_number' => $this->whatsapp_number, 'email' => $this->email, 'notes' => $this->notes, 'is_active' => $this->is_active, 'orders' => OrderResource::collection($this->whenLoaded('orders'))];
    }
}
