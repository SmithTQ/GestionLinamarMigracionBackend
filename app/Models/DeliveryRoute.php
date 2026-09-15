<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_routes';

    protected $fillable = ['campaign_id', 'branch_id', 'courier_id', 'code', 'name', 'description', 'total_distance_km', 'estimated_minutes', 'status', 'dispatched_at'];

    protected function casts(): array
    {
        return ['total_distance_km' => 'decimal:2', 'dispatched_at' => 'datetime'];
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
        return $this->belongsToMany(Order::class, 'route_order', 'route_id', 'order_id')->withPivot(['position', 'is_active', 'assigned_at', 'removed_at'])->withTimestamps();
    }
}
