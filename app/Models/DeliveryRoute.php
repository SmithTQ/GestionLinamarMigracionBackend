<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $active_orders_count
 */
class DeliveryRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_routes';

    protected $fillable = ['campaign_id', 'branch_id', 'courier_id', 'code', 'request_key', 'name', 'description', 'total_distance_km', 'estimated_minutes', 'navigation_url', 'status', 'dispatched_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['total_distance_km' => 'decimal:2', 'dispatched_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'route_order', 'route_id', 'order_id')->withPivot(['position', 'sort_order', 'is_active', 'assigned_at', 'removed_at'])->withTimestamps();
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(RouteAccessToken::class, 'delivery_route_id');
    }

    public function deliveryEvidences(): HasMany
    {
        return $this->hasMany(OrderDeliveryEvidence::class, 'delivery_route_id');
    }
}
