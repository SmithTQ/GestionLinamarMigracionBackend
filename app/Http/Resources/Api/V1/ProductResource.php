<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subcategory_id' => $this->subcategory_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'unit' => $this->unit,
            'base_price' => $this->base_price,
            'image_url' => $this->image_url,
            'image_thumbnail_url' => $this->image_thumbnail_url,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subcategory' => new ProductSubcategoryResource($this->whenLoaded('subcategory')),
            'campaign_pivot' => $this->whenPivotLoaded('campaign_product', fn () => [
                'price' => $this->formatMoney($this->pivot->price),
                'is_available' => (bool) $this->pivot->is_available,
                'sort_order' => $this->pivot->sort_order,
                'max_quantity' => $this->pivot->max_quantity,
            ]),
        ];
    }

    private function formatMoney(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
