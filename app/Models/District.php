<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'province', 'department', 'macroregion', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function campaigns() { return $this->belongsToMany(Campaign::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
