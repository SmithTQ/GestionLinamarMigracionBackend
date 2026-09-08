<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_is_idempotent_and_status_transitions_are_validated(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = Campaign::create(['code' => 'CAMP-01', 'name' => 'Campaña 01', 'status' => 'open']);
        $campaign->branches()->attach($branch);

        $payload = [
            'campaign_id' => $campaign->id,
            'branch_id' => $branch->id,
            'external_source' => 'google_sheets',
            'external_key' => 'fila-001',
            'product_name' => 'Ramo de rosas',
            'sender_name' => 'Ana Torres',
            'sender_phone' => '987654321',
            'recipient_name' => 'Luis Pérez',
            'recipient_phone' => '966554433',
            'district' => 'Miraflores',
            'address' => 'Av. Arequipa 100',
            'delivery_date' => '2026-10-01',
            'delivery_time' => '10:00',
        ];

        $first = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertCreated();
        $second = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertOk();

        $this->assertSame($first->json('datos.id'), $second->json('datos.id'));
        $this->assertDatabaseCount('orders', 1);

        $this->withToken($token)
            ->patchJson('/api/v1/orders/'.$first->json('datos.id').'/status', ['status' => 'validated'])
            ->assertOk()
            ->assertJsonPath('datos.status', 'validated');

        $this->withToken($token)
            ->patchJson('/api/v1/orders/'.$first->json('datos.id').'/status', ['status' => 'delivered'])
            ->assertStatus(422);
    }
}
