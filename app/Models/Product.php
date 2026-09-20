<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'subcategory_id', 'sku', 'name', 'slug', 'description', 'unit', 'base_price',
        'image_url', 'image_path', 'image_thumbnail_path', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function getImageThumbnailUrlAttribute(): ?string
    {
        if (! $this->image_thumbnail_path) {
            return null;
        }

        return Storage::disk(config('filesystems.product_disk', 'public'))
            ->url($this->image_thumbnail_path);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)->withPivot(['price', 'is_available', 'sort_order', 'max_quantity'])->withTimestamps();
    }
}
