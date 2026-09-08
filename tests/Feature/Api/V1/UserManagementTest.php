<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_user_with_role_and_list_catalogs(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $viewer = Role::where('slug', 'viewer')->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/users', [
                'name' => 'Usuario Operativo',
                'username' => 'operativo',
                'email' => 'operativo@example.test',
                'password' => 'Secret1234',
                'password_confirmation' => 'Secret1234',
                'role_ids' => [$viewer->id],
            ])
            ->assertCreated()
            ->assertJsonPath('datos.username', 'operativo')
            ->assertJsonPath('datos.roles.0.slug', 'viewer');

        $this->withToken($token)->getJson('/api/v1/roles')->assertOk()->assertJsonCount(5, 'datos');
        $this->withToken($token)->getJson('/api/v1/permissions')->assertOk()->assertJsonCount(14, 'datos');
    }

    public function test_user_cannot_deactivate_itself(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/users/'.$actor->id)
            ->assertStatus(422);
    }
}
