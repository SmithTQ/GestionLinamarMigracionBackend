<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'budget', 'branch_id', 'status', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['budget' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_active')->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }

    public function districtLists(): BelongsToMany
    {
        return $this->belongsToMany(DistrictList::class, 'campaign_district_lists')->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot(['price', 'is_available', 'sort_order', 'max_quantity'])->withTimestamps();
    }

    public function forms(): HasMany
    {
        return $this->hasMany(CampaignForm::class);
    }
}
