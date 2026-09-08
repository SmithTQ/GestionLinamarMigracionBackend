<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'status', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'campaign_branch');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function routes()
    {
        return $this->hasMany(DeliveryRoute::class);
    }

    public function districts()
    {
        return $this->belongsToMany(District::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot(['price', 'is_available', 'sort_order', 'max_quantity'])->withTimestamps();
    }

    public function forms()
    {
        return $this->hasMany(CampaignForm::class);
    }
}
