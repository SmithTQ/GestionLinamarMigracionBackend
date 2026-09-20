<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\District;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_departments_and_provinces_are_listed_by_ubigeo_codes(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        District::create([
            'code' => '150101', 'name' => 'Lima', 'province' => 'Lima', 'department' => 'Lima',
            'department_code' => '15', 'province_code' => '1501', 'is_active' => true,
        ]);

        $this->withToken($token)->getJson('/api/v1/districts/departments')
            ->assertOk()
            ->assertJsonPath('datos.0.code', '15')
            ->assertJsonPath('datos.0.name', 'Lima');

        $this->withToken($token)->getJson('/api/v1/districts/provinces?department_code=15')
            ->assertOk()
            ->assertJsonPath('datos.0.code', '1501')
            ->assertJsonPath('datos.0.name', 'Lima');
    }

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
        $districtList = $this->withToken($token)->postJson('/api/v1/district-lists', [
            'branch_id' => $branch->id,
            'code' => 'LIMA-COVERAGE',
            'name' => 'Cobertura Lima',
            'district_ids' => [$district['id']],
        ])->assertCreated()->json('datos');
        $campaign = $this->withToken($token)->postJson('/api/v1/campaigns', [
            'code' => 'CAMP-DISTRICT-01',
            'name' => 'Campaña con distrito',
            'status' => 'open',
            'branch_id' => $branch->id,
            'district_list_ids' => [$districtList['id']],
        ])->assertCreated()->assertJsonPath('datos.district_lists.0.code', 'LIMA-COVERAGE')->json('datos');

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
            'delivery_reference' => 'Casa azul frente al parque',
            'latitude' => -12.1211,
            'longitude' => -77.0302,
            'delivery_time' => '14:00 - 16:00',
        ])->assertCreated()->assertJsonPath('datos.district_id', $district['id']);

        $otherDistrict = $this->withToken($token)->postJson('/api/v1/districts', [
            'code' => 'LIM-SURCO',
            'name' => 'Santiago de Surco',
        ])->assertCreated()->json('datos');

        $this->withToken($token)->postJson('/api/v1/orders', [
            'campaign_id' => $campaign['id'], 'branch_id' => $branch->id,
            'product_name' => 'Ramo', 'sender_name' => 'Ana', 'sender_phone' => '987654321',
            'recipient_name' => 'Luis', 'recipient_phone' => '966554433', 'district' => 'Surco',
            'district_id' => $otherDistrict['id'], 'address' => 'Jr. Test 1', 'delivery_reference' => 'Casa azul frente al parque', 'delivery_time' => '14:00 - 16:00',
        ])->assertStatus(422);
    }
}
