<?php

namespace App\Http\Resources\Api\V1;

use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin District */
class DistrictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'province' => $this->province,
            'province_code' => $this->province_code,
            'department' => $this->department,
            'department_code' => $this->department_code,
            'macroregion' => $this->macroregion,
            'is_active' => $this->is_active,
        ];
    }
}
