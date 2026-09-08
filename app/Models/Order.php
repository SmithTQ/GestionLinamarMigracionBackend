<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'district', 'address', 'dedication', 'delivery_date', 'delivery_time', 'imported_at', 'product_price',
        'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'delivery_time' => 'datetime:H:i',
            'imported_at' => 'datetime',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'decimal:2',
            'product_price' => 'decimal:2',
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function routes()
    {
        return $this->belongsToMany(DeliveryRoute::class, 'route_order', 'order_id', 'route_id')->withPivot(['position', 'is_active', 'assigned_at', 'removed_at'])->withTimestamps();
    }

    public function districtCatalog()
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
