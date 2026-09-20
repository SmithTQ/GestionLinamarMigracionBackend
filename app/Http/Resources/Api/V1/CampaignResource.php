<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Campaign
 *
 * @property string|int|float|null $total_obtained
 */
#[OA\Schema(
    schema: 'CampaignResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'CAMP-001'),
        new OA\Property(property: 'name', type: 'string', example: 'Campaña de ejemplo'),
        new OA\Property(property: 'budget', type: 'string', nullable: true, example: '2500.00'),
        new OA\Property(property: 'orders_count', type: 'integer', example: 12),
        new OA\Property(property: 'delivered_count', type: 'integer', example: 8),
        new OA\Property(property: 'total_obtained', type: 'string', nullable: true, example: '1250.00'),
        new OA\Property(property: 'status', type: 'string', example: 'open'),
        new OA\Property(property: 'form_status', type: 'string', nullable: true, example: 'published'),
        new OA\Property(property: 'has_published_form', type: 'boolean', example: true),
    ],
)]
class CampaignResource extends JsonResource
{
    use FormatsResourceDates;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'budget' => $this->budget,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'delivered_count' => (int) ($this->delivered_count ?? 0),
            'total_obtained' => $this->total_obtained === null ? null : number_format((float) $this->total_obtained, 2, '.', ''),
            'status' => $this->status,
            'form_status' => $this->form_status,
            'has_published_form' => (bool) $this->has_published_form,
            'starts_on' => $this->formatResourceDate($this->starts_on, 'd/m/Y'),
            'starts_on_iso' => $this->formatResourceDateOnly($this->starts_on),
            'ends_on' => $this->formatResourceDate($this->ends_on, 'd/m/Y'),
            'ends_on_iso' => $this->formatResourceDateOnly($this->ends_on),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'district_lists' => DistrictListResource::collection($this->whenLoaded('districtLists')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->formatResourceDate($this->created_at, 'd/m/Y H:i'),
            'created_at_iso' => $this->formatResourceDateIso($this->created_at),
            'updated_at' => $this->formatResourceDate($this->updated_at, 'd/m/Y H:i'),
            'updated_at_iso' => $this->formatResourceDateIso($this->updated_at),
        ];
    }
}
