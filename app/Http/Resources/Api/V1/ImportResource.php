<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'branch_id' => $this->branch_id,
            'source' => $this->source,
            'range' => $this->range,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'inserted_rows' => $this->inserted_rows,
            'duplicated_rows' => $this->duplicated_rows,
            'invalid_rows' => $this->invalid_rows,
            'failed_rows' => $this->failed_rows,
            'errors' => $this->errors,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
