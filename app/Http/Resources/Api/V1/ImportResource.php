<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Import */
class ImportResource extends JsonResource
{
    use FormatsResourceDates;

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
            'started_at' => $this->formatResourceDate($this->started_at, 'd/m/Y H:i'),
            'started_at_iso' => $this->formatResourceDateIso($this->started_at),
            'completed_at' => $this->formatResourceDate($this->completed_at, 'd/m/Y H:i'),
            'completed_at_iso' => $this->formatResourceDateIso($this->completed_at),
        ];
    }
}
