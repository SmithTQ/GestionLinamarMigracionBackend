<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSubcategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function category() { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function products() { return $this->hasMany(Product::class, 'subcategory_id'); }
}
