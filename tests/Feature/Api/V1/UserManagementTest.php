<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
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
        $this->withToken($token)->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonCount(20, 'datos')
            ->assertJsonFragment(['slug' => 'districts.view'])
            ->assertJsonFragment(['slug' => 'district_lists.view'])
            ->assertJsonFragment(['slug' => 'forms.manage'])
            ->assertJsonFragment(['slug' => 'orders.manage']);
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

    public function test_campaign_manager_only_lists_dispatchers_from_selected_branch(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $branch = Branch::create(['code' => 'USR-01', 'name' => 'Sucursal usuarios']);
        $otherBranch = Branch::create(['code' => 'USR-02', 'name' => 'Otra sucursal']);
        $manager->branches()->attach($branch);

        $dispatcher = User::factory()->create(['name' => 'Despachador visible', 'is_active' => true]);
        $dispatcher->roles()->attach(Role::where('slug', 'dispatcher')->firstOrFail());
        $dispatcher->branches()->attach($branch);
        $otherDispatcher = User::factory()->create(['name' => 'Despachador ajeno', 'is_active' => true]);
        $otherDispatcher->roles()->attach(Role::where('slug', 'dispatcher')->firstOrFail());
        $otherDispatcher->branches()->attach($otherBranch);

        $token = $manager->createToken('angular')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users?branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $dispatcher->id])
            ->assertJsonMissing(['name' => 'Despachador ajeno']);

        $this->withToken($token)->getJson('/api/v1/users')->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/roles')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.slug', 'dispatcher');
    }

    public function test_campaign_manager_cannot_manage_administrators_or_preassign_campaigns(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $branch = Branch::create(['code' => 'USR-03', 'name' => 'Sucursal operativa']);
        $manager->branches()->attach($branch);
        $dispatcherRole = Role::where('slug', 'dispatcher')->firstOrFail();
        $viewerRole = Role::where('slug', 'viewer')->firstOrFail();
        $campaign = Campaign::create(['code' => 'USR-CAMP', 'name' => 'Campaña usuarios', 'status' => 'open', 'branch_id' => $branch->id]);
        $token = $manager->createToken('angular')->plainTextToken;

        $payload = [
            'name' => 'Nuevo despachador',
            'username' => 'nuevo_despachador',
            'email' => 'nuevo-despachador@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'role_ids' => [$dispatcherRole->id],
            'branch_ids' => [$branch->id],
        ];

        $this->withToken($token)->postJson('/api/v1/users', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/users', array_merge($payload, [
            'username' => 'otro_despachador',
            'email' => 'otro-despachador@example.test',
            'campaign_ids' => [$campaign->id],
        ]))->assertStatus(422);
        $this->withToken($token)->postJson('/api/v1/users', array_merge($payload, [
            'username' => 'usuario_admin',
            'email' => 'usuario-admin@example.test',
            'role_ids' => [$viewerRole->id],
        ]))->assertForbidden();
    }

    public function test_campaign_manager_can_assign_only_same_branch_dispatchers(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $branch = Branch::create(['code' => 'USR-04', 'name' => 'Sucursal campaña']);
        $otherBranch = Branch::create(['code' => 'USR-05', 'name' => 'Sucursal externa']);
        $manager->branches()->attach($branch);
        $campaign = Campaign::create(['code' => 'USR-CAMP-2', 'name' => 'Campaña despacho', 'status' => 'open', 'branch_id' => $branch->id]);
        $valid = User::factory()->create(['is_active' => true]);
        $valid->roles()->attach(Role::where('slug', 'dispatcher')->firstOrFail());
        $valid->branches()->attach($branch);
        $invalid = User::factory()->create(['is_active' => true]);
        $invalid->roles()->attach(Role::where('slug', 'dispatcher')->firstOrFail());
        $invalid->branches()->attach($otherBranch);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $admin->branches()->attach($branch);
        $token = $manager->createToken('angular')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/campaigns/'.$campaign->id.'/available-dispatchers')
            ->assertOk()
            ->assertJsonFragment(['id' => $valid->id])
            ->assertJsonMissing(['name' => $invalid->name]);
        $this->withToken($token)->postJson('/api/v1/campaigns/'.$campaign->id.'/users', ['user_id' => $valid->id])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/campaigns/'.$campaign->id.'/users', ['user_id' => $invalid->id])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/campaigns/'.$campaign->id.'/users', ['user_id' => $admin->id])->assertForbidden();
    }
}
