<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'description', 'is_available', 'is_active'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'is_active' => 'boolean'];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }
}
