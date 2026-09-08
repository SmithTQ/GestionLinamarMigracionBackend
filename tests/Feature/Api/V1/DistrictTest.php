<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_can_be_assigned_to_campaign_and_used_with_map_location(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        $district = $this->withToken($token)->postJson('/api/v1/districts', [
            'code' => 'LIM-MIRAFLORES',
            'name' => 'Miraflores',
            'province' => 'Lima',
            'department' => 'Lima',
        ])->assertCreated()->json('datos');

        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = $this->withToken($token)->postJson('/api/v1/campaigns', [
            'code' => 'CAMP-DISTRICT-01',
            'name' => 'Campaña con distrito',
            'status' => 'open',
            'branch_ids' => [$branch->id],
            'district_ids' => [$district['id']],
        ])->assertCreated()->assertJsonPath('datos.districts.0.code', 'LIM-MIRAFLORES')->json('datos');

        $this->withToken($token)->postJson('/api/v1/orders', [
            'campaign_id' => $campaign['id'],
            'branch_id' => $branch->id,
            'product_name' => 'Ramo',
            'sender_name' => 'Ana',
            'sender_phone' => '987654321',
            'recipient_name' => 'Luis',
            'recipient_phone' => '966554433',
            'district' => 'Miraflores',
            'district_id' => $district['id'],
            'address' => 'Av. Arequipa 100',
            'latitude' => -12.1211,
            'longitude' => -77.0302,
        ])->assertCreated()->assertJsonPath('datos.district_id', $district['id']);

        $otherDistrict = $this->withToken($token)->postJson('/api/v1/districts', [
            'code' => 'LIM-SURCO',
            'name' => 'Santiago de Surco',
        ])->assertCreated()->json('datos');

        $this->withToken($token)->postJson('/api/v1/orders', [
            'campaign_id' => $campaign['id'], 'branch_id' => $branch->id,
            'product_name' => 'Ramo', 'sender_name' => 'Ana', 'sender_phone' => '987654321',
            'recipient_name' => 'Luis', 'recipient_phone' => '966554433', 'district' => 'Surco',
            'district_id' => $otherDistrict['id'], 'address' => 'Jr. Test 1',
        ])->assertStatus(422);
    }
}
