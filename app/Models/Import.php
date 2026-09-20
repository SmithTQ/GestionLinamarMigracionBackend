<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'branch_id', 'requested_by', 'source', 'spreadsheet_id', 'range', 'status', 'total_rows', 'inserted_rows', 'duplicated_rows', 'invalid_rows', 'failed_rows', 'errors', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['errors' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
