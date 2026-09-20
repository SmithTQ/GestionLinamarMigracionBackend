<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'pending', 'validated', 'planned', 'assigned', 'in_transit', 'delivered', 'failed', 'cancelled',
    ];

    protected $fillable = [
        'campaign_id', 'branch_id', 'customer_id', 'product_id', 'external_source', 'external_key', 'order_number',
        'district_id', 'latitude', 'longitude', 'location_accuracy',
        'product_name', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone',
        'district', 'address', 'delivery_reference', 'dedication', 'delivery_date', 'delivery_time', 'imported_at', 'product_price',
        'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'imported_at' => 'datetime',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'decimal:2',
            'product_price' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryRoute::class, 'route_order', 'order_id', 'route_id')->withPivot(['position', 'sort_order', 'is_active', 'assigned_at', 'removed_at'])->withTimestamps();
    }

    public function activeRoutes(): BelongsToMany
    {
        return $this->routes()->wherePivot('is_active', true);
    }

    public function districtCatalog(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function formSubmission(): HasOne
    {
        return $this->hasOne(FormSubmission::class);
    }

    public function deliveryEvidences(): HasMany
    {
        return $this->hasMany(OrderDeliveryEvidence::class);
    }
}
