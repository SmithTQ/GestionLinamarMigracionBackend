<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignResource extends JsonResource
{
    use FormatsResourceDates;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'form_status' => $this->form_status,
            'has_published_form' => (bool) $this->has_published_form,
            'starts_on' => $this->formatResourceDate($this->starts_on, 'd/m/Y'),
            'starts_on_iso' => $this->formatResourceDateOnly($this->starts_on),
            'ends_on' => $this->formatResourceDate($this->ends_on, 'd/m/Y'),
            'ends_on_iso' => $this->formatResourceDateOnly($this->ends_on),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'district_lists' => DistrictListResource::collection($this->whenLoaded('districtLists')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->formatResourceDate($this->created_at, 'd/m/Y H:i'),
            'created_at_iso' => $this->formatResourceDateIso($this->created_at),
            'updated_at' => $this->formatResourceDate($this->updated_at, 'd/m/Y H:i'),
            'updated_at_iso' => $this->formatResourceDateIso($this->updated_at),
        ];
    }
}
