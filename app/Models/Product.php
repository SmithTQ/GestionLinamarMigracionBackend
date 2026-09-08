<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subcategory_id', 'sku', 'name', 'slug', 'description', 'unit', 'base_price',
        'image_url', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function subcategory() { return $this->belongsTo(ProductSubcategory::class, 'subcategory_id'); }
    public function campaigns() { return $this->belongsToMany(Campaign::class)->withPivot(['price', 'is_available', 'sort_order', 'max_quantity'])->withTimestamps(); }
}
