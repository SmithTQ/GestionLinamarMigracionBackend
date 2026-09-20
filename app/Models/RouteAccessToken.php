<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteAccessToken extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_route_id', 'token_hash', 'expires_at', 'revoked_at', 'last_used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }
}
