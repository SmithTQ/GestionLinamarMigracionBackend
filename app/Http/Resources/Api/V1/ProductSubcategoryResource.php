<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductSubcategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductSubcategory */
class ProductSubcategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'category_id' => $this->category_id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order, 'category' => new ProductCategoryResource($this->whenLoaded('category')), 'products' => ProductResource::collection($this->whenLoaded('products'))];
    }
}
