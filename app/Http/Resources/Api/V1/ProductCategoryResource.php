<?php
namespace App\Http\Resources\Api\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProductCategoryResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'name'=>$this->name,'slug'=>$this->slug,'description'=>$this->description,'is_active'=>$this->is_active,'sort_order'=>$this->sort_order,'subcategories'=>ProductSubcategoryResource::collection($this->whenLoaded('subcategories'))]; } }
