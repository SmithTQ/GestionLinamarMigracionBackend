<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_manager_can_query_assigned_campaign_scope_without_sql_errors(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $role = Role::where('slug', 'campaign_manager')->firstOrFail();
        $manager->roles()->attach($role);
        $token = $manager->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'SCOPE-01', 'name' => 'Sucursal ámbito', 'latitude' => -12.02, 'longitude' => -77.03]);
        $campaign = Campaign::create(['code' => 'SCOPE-CAMP', 'name' => 'Campaña ámbito', 'status' => 'open', 'branch_id' => $branch->id]);
        $campaign->users()->attach($manager, ['is_active' => true]);
        $branch->users()->attach($manager);
        Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'status' => 'validated', 'is_active' => true, 'latitude' => -12.04, 'longitude' => -77.05,
        ]);

        $this->withToken($token)->getJson('/api/v1/campaigns/available?branch_id='.$branch->id)->assertOk();
        $this->withToken($token)->getJson('/api/v1/campaigns')->assertOk();
        $this->withToken($token)->getJson('/api/v1/orders?campaign_id='.$campaign->id)->assertOk();
        $this->withToken($token)->getJson('/api/v1/campaign-forms?campaign_id='.$campaign->id)->assertOk();
        $this->withToken($token)->getJson('/api/v1/customer-invitations')->assertOk();
        $this->withToken($token)->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaign->id)->assertOk();
    }

    public function test_campaign_manager_uses_branch_scope_without_campaign_assignment(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $branch = Branch::create(['code' => 'SCOPE-BRANCH', 'name' => 'Sucursal operativa']);
        $branch->users()->attach($manager);
        $campaign = Campaign::create(['code' => 'SCOPE-BRANCH-CAMP', 'name' => 'Campana por sucursal', 'status' => 'open', 'branch_id' => $branch->id]);

        $this->actingAs($manager)->getJson('/api/v1/campaigns')->assertOk()->assertJsonPath('datos.0.id', $campaign->id);
    }

    public function test_available_campaigns_apply_the_correct_branch_rule_per_role(): void
    {
        $this->seed();
        $branch = Branch::create(['code' => 'SCOPE-ROLE', 'name' => 'Sucursal por rol']);
        $campaign = Campaign::create([
            'code' => 'SCOPE-ROLE-CAMP',
            'name' => 'Campana asignada al despachador',
            'status' => 'open',
            'branch_id' => $branch->id,
        ]);

        $dispatcher = User::factory()->create(['is_active' => true]);
        $dispatcher->roles()->attach(Role::where('slug', 'dispatcher')->firstOrFail());
        $campaign->users()->attach($dispatcher, ['is_active' => true]);

        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $branch->users()->attach($manager);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());

        $this->actingAs($dispatcher)
            ->getJson('/api/v1/campaigns/available')
            ->assertOk()
            ->assertJsonPath('datos.0.id', $campaign->id);

        $this->actingAs($manager)
            ->getJson('/api/v1/campaigns/available')
            ->assertStatus(422)
            ->assertJsonPath('errores.branch_id.0', 'Debes seleccionar una sucursal para consultar este listado.');

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/campaigns/available')
            ->assertOk()
            ->assertJsonPath('datos.0.id', $campaign->id);

        $this->actingAs($dispatcher)
            ->getJson('/api/v1/products')
            ->assertForbidden();

        $this->actingAs($dispatcher)
            ->getJson('/api/v1/district-lists')
            ->assertForbidden();
    }
}
