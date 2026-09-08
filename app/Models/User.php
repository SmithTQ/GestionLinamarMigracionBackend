<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', fn ($query) => $query
                ->where('permissions.slug', $permission)
                ->where('permissions.is_active', true))
            ->exists();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('roles.slug', $role)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
