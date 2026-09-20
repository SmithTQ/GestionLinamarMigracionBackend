<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['full_name', 'whatsapp_number', 'email', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(FormInvitation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
