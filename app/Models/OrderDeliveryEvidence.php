<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderDeliveryEvidence extends Model
{
    use HasFactory;

    protected $table = 'order_delivery_evidences';

    protected $fillable = [
        'order_id', 'delivery_route_id', 'delivered_by_courier_id', 'disk', 'path',
        'thumbnail_path', 'mime_type', 'size', 'note', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'size' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'delivered_by_courier_id');
    }

    public function url(string $token, int $orderId): string
    {
        return url('/api/v1/public/routes/'.$token.'/orders/'.$orderId.'/delivery-evidence');
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
