<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    protected $fillable = ['campaign_form_id', 'order_id', 'submission_key', 'request_hash', 'payload', 'status', 'ip_hash'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CampaignForm::class, 'campaign_form_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(FormSubmissionFile::class, 'form_submission_id');
    }
}
