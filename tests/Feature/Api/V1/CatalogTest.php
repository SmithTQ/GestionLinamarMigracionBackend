<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_campaign_and_branch(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        $branch = $this->withToken($token)->postJson('/api/v1/branches', [
            'code' => 'LIMA-01',
            'name' => 'Sucursal Lima',
        ])->assertCreated()->json('datos');

        $this->withToken($token)->postJson('/api/v1/campaigns', [
            'code' => 'CAMP-2026-01',
            'name' => 'Campaña inicial',
            'status' => 'open',
            'branch_ids' => [$branch['id']],
        ])->assertCreated()
            ->assertJsonPath('datos.branches.0.code', 'LIMA-01');
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('angular')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No tienes permiso para realizar esta operación.');
    }
}
