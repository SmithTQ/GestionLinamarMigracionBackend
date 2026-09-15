<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_can_assign_courier_attach_order_and_dispatch(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = Campaign::create(['code' => 'CAMP-01', 'name' => 'Campaña 01', 'status' => 'open']);
        $campaign->branches()->attach($branch);
        $courier = Courier::create(['name' => 'Carlos Motorizado', 'phone' => '999111222']);
        $courier->branches()->attach($branch);
        $order = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
        ]);

        $route = $this->withToken($token)->postJson('/api/v1/routes', [
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-01', 'name' => 'Ruta Lima 01',
        ])->assertCreated()->json('datos');

        $this->withToken($token)->postJson('/api/v1/routes/'.$route['id'].'/orders', ['order_ids' => [$order->id]])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/status', ['status' => 'planned'])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/courier', ['courier_id' => $courier->id])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/status', ['status' => 'dispatched'])->assertOk();

        $this->assertDatabaseHas('delivery_routes', ['id' => $route['id'], 'status' => 'dispatched', 'courier_id' => $courier->id]);
        $this->assertDatabaseHas('route_order', ['route_id' => $route['id'], 'order_id' => $order->id, 'is_active' => 1]);
    }
}
