<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'province', 'department', 'macroregion', 'is_active',
        'department_code', 'province_code',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function districtLists(): BelongsToMany
    {
        return $this->belongsToMany(DistrictList::class, 'district_list_items')->withPivot('sort_order');
    }
}
