<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInvitation extends Model
{
    protected $fillable = ['campaign_form_id', 'customer_id', 'order_id', 'created_by_user_id', 'revoked_by_user_id', 'token', 'status', 'expires_at', 'used_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CampaignForm::class, 'campaign_form_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
