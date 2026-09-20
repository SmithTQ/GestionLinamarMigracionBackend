<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\RouteAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_route_can_confirm_delivery_with_evidence_idempotently(): void
    {
        Storage::fake('local');
        $this->seed();
        $branch = Branch::create(['code' => 'EVIDENCE-01', 'name' => 'Sucursal evidencia']);
        $campaign = Campaign::create(['code' => 'EVIDENCE-CAMP', 'name' => 'Campana evidencia', 'status' => 'open', 'branch_id' => $branch->id]);
        $product = Product::create(['branch_id' => $branch->id, 'sku' => 'EVIDENCE-001', 'name' => 'Producto evidencia', 'slug' => 'producto-evidencia', 'base_price' => 50]);
        $courier = Courier::create(['name' => 'Motorizado evidencia', 'phone' => '51999999990']);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'courier_id' => $courier->id, 'code' => 'RUTA-EVIDENCE', 'name' => 'Ruta evidencia', 'status' => 'assigned']);
        $order = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis', 'recipient_phone' => '999000222',
            'district' => 'Miraflores', 'address' => 'Av. Lima 1', 'delivery_reference' => 'Casa azul', 'status' => 'assigned',
        ]);
        $route->orders()->attach($order->id, ['sort_order' => 1, 'position' => 1, 'is_active' => true]);
        $plainToken = 'public-evidence-token';
        RouteAccessToken::create(['delivery_route_id' => $route->id, 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDay()]);

        $this->getJson('/api/v1/public/routes/'.$plainToken)
            ->assertOk()
            ->assertJsonPath('datos.orders.0.order_id', $order->id)
            ->assertJsonPath('datos.orders.0.product.name', 'Producto evidencia')
            ->assertJsonPath('datos.orders.0.sender.name', 'Ana')
            ->assertJsonPath('datos.orders.0.delivery_evidence', null);

        $response = $this->post('/api/v1/public/routes/'.$plainToken.'/orders/'.$order->id.'/delivery-confirmation', [
            'evidence' => UploadedFile::fake()->image('delivery.jpg'),
            'note' => 'Entregado en recepción',
        ])->assertOk();
        $response->assertJsonPath('datos.orders.0.status', 'delivered');
        $response->assertJsonPath('datos.orders.0.delivery_evidence.delivered_by_courier_id', $courier->id);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseCount('order_delivery_evidences', 1);

        $this->post('/api/v1/public/routes/'.$plainToken.'/orders/'.$order->id.'/delivery-confirmation', [
            'evidence' => UploadedFile::fake()->image('retry.jpg'),
        ])->assertOk()->assertJsonPath('datos.orders.0.status', 'delivered');
        $this->assertDatabaseCount('order_delivery_evidences', 1);
    }

    public function test_public_delivery_confirmation_rejects_invalid_token_order_and_file(): void
    {
        Storage::fake('local');
        $this->seed();
        $branch = Branch::create(['code' => 'EVIDENCE-02', 'name' => 'Sucursal evidencia 2']);
        $campaign = Campaign::create(['code' => 'EVIDENCE-CAMP-2', 'name' => 'Campana evidencia 2', 'status' => 'open', 'branch_id' => $branch->id]);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-EVIDENCE-2', 'name' => 'Ruta evidencia 2', 'status' => 'assigned']);
        $plainToken = 'expired-evidence-token';
        RouteAccessToken::create(['delivery_route_id' => $route->id, 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/public/routes/'.$plainToken)->assertNotFound();
        $validToken = 'valid-evidence-token';
        RouteAccessToken::create(['delivery_route_id' => $route->id, 'token_hash' => hash('sha256', $validToken), 'expires_at' => now()->addDay()]);
        $this->post('/api/v1/public/routes/'.$validToken.'/orders/999/delivery-confirmation', ['evidence' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')])->assertUnprocessable();
    }

    public function test_route_public_access_token_is_hashed_expirable_and_revocable(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $authToken = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'TOKEN-01', 'name' => 'Sucursal token']);
        $campaign = Campaign::create(['code' => 'TOKEN-CAMP', 'name' => 'Campana token', 'status' => 'open', 'branch_id' => $branch->id]);
        $order = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
        ]);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-TOKEN', 'name' => 'Ruta token', 'status' => 'planned']);
        $route->orders()->attach($order->id, ['sort_order' => 1, 'position' => 1, 'is_active' => true]);

        $token = $this->withToken($authToken)->postJson('/api/v1/routes/'.$route->id.'/access-token')->assertCreated()->json('datos.token');
        $this->assertNotSame($token, RouteAccessToken::firstOrFail()->token_hash);
        $this->getJson('/api/v1/public/routes/'.$token)
            ->assertOk()
            ->assertJsonPath('datos.code', 'RUTA-TOKEN')
            ->assertJsonPath('datos.orders.0.recipient_name', 'Luis')
            ->assertJsonMissingPath('datos.id')
            ->assertJsonMissingPath('datos.campaign_id');

        $this->withToken($authToken)->deleteJson('/api/v1/routes/'.$route->id.'/access-token')->assertOk();
        $this->getJson('/api/v1/public/routes/'.$token)->assertNotFound();
    }

    public function test_courier_invitation_assigns_reuses_and_rotates_route_token(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $authToken = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'INV-01', 'name' => 'Sucursal invitación']);
        $campaign = Campaign::create(['code' => 'INV-CAMP', 'name' => 'Campaña invitación', 'status' => 'open', 'branch_id' => $branch->id]);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-INV', 'name' => 'Ruta invitación', 'status' => 'planned']);

        $first = $this->withToken($authToken)->postJson('/api/v1/routes/'.$route->id.'/courier-invitations', [
            'name' => 'Juan Perez',
            'whatsapp_number' => '+51 (999) 999-999',
        ])->assertCreated()->assertJsonPath('datos.courier.phone', '51999999999');
        $firstToken = RouteAccessToken::query()->where('delivery_route_id', $route->id)->firstOrFail();
        $firstPublicUrl = $first->json('datos.public_url');
        $this->assertStringStartsWith('http://localhost:4200/ruta/', $firstPublicUrl);
        $this->assertStringContainsString('wa.me/51999999999', $first->json('datos.whatsapp_url'));
        $this->assertNotSame($firstToken->token_hash, basename($firstPublicUrl));
        $first->assertJsonPath('datos.route.status', 'assigned');

        $second = $this->withToken($authToken)->postJson('/api/v1/routes/'.$route->id.'/courier-invitations', [
            'name' => 'Juan Perez actualizado',
            'whatsapp_number' => '51999999999',
            'expires_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $this->assertSame(1, Courier::where('phone', '51999999999')->count());
        $this->assertNotNull($firstToken->fresh()->revoked_at);
        $this->assertSame(2, RouteAccessToken::where('delivery_route_id', $route->id)->count());
        $this->assertSame('Juan Perez actualizado', $second->json('datos.courier.name'));
        $this->assertNotSame($first->json('datos.public_url'), $second->json('datos.public_url'));
        $this->getJson('/api/v1/public/routes/'.basename($second->json('datos.public_url')))->assertOk();
    }

    public function test_courier_invitation_rejects_courier_from_another_branch(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $authToken = $actor->createToken('angular')->plainTextToken;
        $routeBranch = Branch::create(['code' => 'INV-02', 'name' => 'Sucursal ruta']);
        $otherBranch = Branch::create(['code' => 'INV-03', 'name' => 'Sucursal ajena']);
        $campaign = Campaign::create(['code' => 'INV-CAMP-2', 'name' => 'Campaña invitación 2', 'status' => 'open', 'branch_id' => $routeBranch->id]);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $routeBranch->id, 'code' => 'RUTA-INV-2', 'name' => 'Ruta invitación 2', 'status' => 'planned']);
        $courier = Courier::create(['name' => 'Motorizado ajeno', 'phone' => '51988888888', 'is_available' => true, 'is_active' => true]);
        $courier->branches()->attach($otherBranch);

        $this->withToken($authToken)->postJson('/api/v1/routes/'.$route->id.'/courier-invitations', [
            'name' => 'Motorizado ajeno',
            'whatsapp_number' => '51988888888',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('delivery_routes', ['id' => $route->id, 'courier_id' => null, 'status' => 'planned']);
        $this->assertDatabaseCount('route_access_tokens', 0);
    }

    public function test_courier_invitation_respects_route_scope(): void
    {
        $this->seed();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $managedBranch = Branch::create(['code' => 'INV-04', 'name' => 'Sucursal administrada']);
        $foreignBranch = Branch::create(['code' => 'INV-05', 'name' => 'Sucursal fuera']);
        $manager->branches()->attach($managedBranch);
        $campaign = Campaign::create(['code' => 'INV-CAMP-3', 'name' => 'Campaña fuera', 'status' => 'open', 'branch_id' => $foreignBranch->id]);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $foreignBranch->id, 'code' => 'RUTA-INV-3', 'name' => 'Ruta fuera', 'status' => 'planned']);
        $authToken = $manager->createToken('angular')->plainTextToken;

        $this->withToken($authToken)->postJson('/api/v1/routes/'.$route->id.'/courier-invitations', [
            'name' => 'Juan Perez',
            'whatsapp_number' => '51977777777',
        ])->assertNotFound();
    }

    public function test_route_can_assign_courier_attach_order_and_dispatch(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = Campaign::create(['code' => 'CAMP-01', 'name' => 'Campaña 01', 'status' => 'open', 'branch_id' => $branch->id]);
        $courier = Courier::create(['name' => 'Carlos Motorizado', 'phone' => '999111222']);
        $courier->branches()->attach($branch);
        $order = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
        ]);

        $routeResponse = $this->withToken($token)->postJson('/api/v1/routes', [
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-01', 'name' => 'Ruta Lima 01',
        ])->assertCreated()->assertJsonPath('datos.cancelled_at', null)->assertJsonPath('datos.cancelled_at_iso', null);
        $route = $routeResponse->json('datos');

        $this->withToken($token)->postJson('/api/v1/routes/'.$route['id'].'/orders', ['order_ids' => [$order->id]])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/status', ['status' => 'planned'])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/courier', ['courier_id' => $courier->id])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/routes/'.$route['id'].'/status', ['status' => 'dispatched'])->assertOk();

        $this->assertDatabaseHas('delivery_routes', ['id' => $route['id'], 'status' => 'dispatched', 'courier_id' => $courier->id]);
        $this->assertDatabaseHas('route_order', ['route_id' => $route['id'], 'order_id' => $order->id, 'is_active' => 1]);
        $this->withToken($token)
            ->getJson('/api/v1/routes')
            ->assertOk()
            ->assertJsonPath('datos.0.active_orders_count', 1);
    }

    public function test_route_can_be_generated_and_stops_reordered_atomically(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-02', 'name' => 'Sucursal Norte', 'latitude' => -12.01, 'longitude' => -77.02]);
        $campaign = Campaign::create(['code' => 'CAMP-02', 'name' => 'Campana 02', 'status' => 'open', 'branch_id' => $branch->id]);
        $orders = collect([
            ['recipient_name' => 'Uno', 'latitude' => -12.02, 'longitude' => -77.03],
            ['recipient_name' => 'Dos', 'latitude' => -12.04, 'longitude' => -77.05],
        ])->map(fn (array $data) => Order::create($data + [
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_phone' => '999000222',
            'district' => 'Miraflores', 'address' => 'Av. Lima 1', 'status' => 'validated', 'is_active' => true,
        ]));

        $eligible = $this->withToken($token)->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaign->id);
        $eligible->assertOk()->assertJsonPath('paginacion.total', 2);

        $route = $this->withToken($token)->postJson('/api/v1/routes/generate', [
            'campaign_id' => $campaign->id, 'name' => 'Ruta Norte',
            'stops' => [
                ['order_id' => $orders[0]->id, 'sort_order' => 1],
                ['order_id' => $orders[1]->id, 'sort_order' => 2],
            ],
            'estimated_distance_km' => 12.5, 'estimated_minutes' => 40, 'request_key' => 'route-request-01',
        ])->assertCreated()->json('datos');

        $this->assertSame('https://www.google.com/maps/dir/', strtok($route['navigation_url'], '?'));
        $this->assertDatabaseHas('orders', ['id' => $orders[0]->id, 'status' => 'planned']);
        $this->withToken($token)->putJson('/api/v1/routes/'.$route['id'].'/stops', [
            'stops' => [
                ['order_id' => $orders[1]->id, 'sort_order' => 1],
                ['order_id' => $orders[0]->id, 'sort_order' => 2],
            ],
        ])->assertOk()->assertJsonPath('datos.orders.0.route_stop.sort_order', 1);

        $this->withToken($token)->postJson('/api/v1/routes/generate', [
            'campaign_id' => $campaign->id, 'name' => 'Duplicada',
            'stops' => [['order_id' => $orders[0]->id, 'sort_order' => 1]], 'request_key' => 'route-request-01',
        ])->assertOk();
        $this->assertDatabaseCount('delivery_routes', 1);
    }

    public function test_route_endpoints_resolve_campaign_branch_and_reject_mismatched_orders(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-03', 'name' => 'Sucursal Centro', 'latitude' => -12.02, 'longitude' => -77.03]);
        $otherBranch = Branch::create(['code' => 'LIMA-04', 'name' => 'Sucursal Sur', 'latitude' => -12.05, 'longitude' => -77.06]);
        $campaign = Campaign::create(['code' => 'CAMP-03', 'name' => 'Campana 03', 'status' => 'open', 'branch_id' => $branch->id]);
        $otherCampaign = Campaign::create(['code' => 'CAMP-04', 'name' => 'Campana 04', 'status' => 'open', 'branch_id' => $otherBranch->id]);
        $order = Order::create([
            'campaign_id' => $otherCampaign->id, 'branch_id' => $otherBranch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'status' => 'validated', 'latitude' => -12.06, 'longitude' => -77.07,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaign->id)
            ->assertOk()
            ->assertJsonPath('paginacion.total', 0);

        $this->withToken($token)
            ->postJson('/api/v1/routes/generate', [
                'campaign_id' => $campaign->id,
                'name' => 'Ruta inválida',
                'stops' => [['order_id' => $order->id, 'sort_order' => 1]],
                'request_key' => 'route-request-invalid-branch',
            ])
            ->assertUnprocessable();

        $campaignWithoutBranch = Campaign::create(['code' => 'CAMP-05', 'name' => 'Sin sucursal', 'status' => 'open']);
        $this->withToken($token)
            ->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaignWithoutBranch->id)
            ->assertUnprocessable();
    }

    public function test_eligible_orders_excludes_active_routes_and_keeps_historical_routes(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-05', 'name' => 'Sucursal Este', 'latitude' => -12.02, 'longitude' => -77.03]);
        $campaign = Campaign::create(['code' => 'CAMP-05', 'name' => 'Campana 05', 'status' => 'open', 'branch_id' => $branch->id]);
        $orders = collect(['Libre', 'Activa', 'Historica'])->map(fn (string $recipient) => Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => $recipient,
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'status' => 'validated', 'latitude' => -12.04, 'longitude' => -77.05,
        ]));
        $activeRoute = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-ACTIVA', 'request_key' => 'eligible-active', 'name' => 'Activa', 'status' => 'planned']);
        $historicalRoute = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-HISTORICA', 'request_key' => 'eligible-history', 'name' => 'Histórica', 'status' => 'completed']);
        $activeRoute->orders()->attach($orders[1]->id, ['sort_order' => 1, 'position' => 1, 'is_active' => true]);
        $historicalRoute->orders()->attach($orders[2]->id, ['sort_order' => 1, 'position' => 1, 'is_active' => false, 'removed_at' => now()]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaign->id.'&per_page=2')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 2);

        $recipients = collect($response->json('datos'))->pluck('recipient_name');
        $this->assertTrue($recipients->contains('Libre'));
        $this->assertTrue($recipients->contains('Historica'));
        $this->assertFalse($recipients->contains('Activa'));
    }

    public function test_map_orders_includes_pending_and_assigned_orders_with_summary(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'MAP-01', 'name' => 'Sucursal mapa']);
        $campaign = Campaign::create(['code' => 'MAP-CAMP', 'name' => 'Campaña mapa', 'status' => 'open', 'branch_id' => $branch->id]);
        $pending = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Pendiente',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Pendiente',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'status' => 'validated', 'is_active' => true, 'latitude' => -12.04, 'longitude' => -77.05,
        ]);
        $assigned = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Asignado',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Asignado',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 2',
            'status' => 'planned', 'is_active' => true, 'latitude' => -12.05, 'longitude' => -77.06,
        ]);
        Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Sin ubicación',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Sin ubicación',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 3',
            'status' => 'validated', 'is_active' => true,
        ]);
        $route = DeliveryRoute::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-MAP',
            'name' => 'Ruta mapa', 'status' => 'planned',
        ]);
        $route->orders()->attach($assigned->id, ['sort_order' => 1, 'position' => 1, 'is_active' => true]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/routes/map-orders?campaign_id='.$campaign->id)
            ->assertOk()
            ->assertJsonPath('resumen.total', 2)
            ->assertJsonPath('resumen.pending', 1)
            ->assertJsonPath('resumen.assigned', 1)
            ->assertJsonPath('paginacion.total', 2)
            ->assertJsonPath('datos.1.active_route.code', 'RUTA-MAP');

        $this->assertSame(['Pendiente', 'Asignado'], collect($response->json('datos'))->pluck('recipient_name')->all());
        $this->assertNull($response->json('datos.0.active_route'));
    }

    public function test_cancelling_route_releases_orders_and_keeps_route_history(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'CANCEL-01', 'name' => 'Sucursal cancelación']);
        $campaign = Campaign::create(['code' => 'CANCEL-CAMP', 'name' => 'Campaña cancelación', 'status' => 'open', 'branch_id' => $branch->id]);
        $orders = collect(['Uno', 'Dos'])->map(fn (string $recipient) => Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => $recipient,
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'status' => 'planned', 'is_active' => true, 'latitude' => -12.04, 'longitude' => -77.05,
        ]));
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'code' => 'RUTA-CANCEL', 'name' => 'Ruta cancelable', 'status' => 'planned']);
        foreach ($orders as $index => $order) {
            $route->orders()->attach($order->id, ['sort_order' => $index + 1, 'position' => $index + 1, 'is_active' => true]);
        }

        $cancelledResponse = $this->withToken($token)->patchJson('/api/v1/routes/'.$route->id.'/status', ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('datos.status', 'cancelled')
            ->assertJsonStructure(['datos' => ['cancelled_at', 'cancelled_at_iso']]);
        $this->assertNotEmpty($cancelledResponse->json('datos.cancelled_at'));
        $this->assertNotEmpty($cancelledResponse->json('datos.cancelled_at_iso'));

        $this->assertDatabaseHas('delivery_routes', ['id' => $route->id, 'status' => 'cancelled']);
        $this->assertNotNull(DeliveryRoute::findOrFail($route->id)->cancelled_at);
        $this->assertDatabaseCount('route_order', 2);
        $this->assertDatabaseMissing('route_order', ['route_id' => $route->id, 'is_active' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $orders[0]->id, 'status' => 'pending']);
        $this->withToken($token)->getJson('/api/v1/routes/eligible-orders?campaign_id='.$campaign->id)
            ->assertOk()->assertJsonPath('paginacion.total', 2);
    }

    public function test_cannot_cancel_terminal_or_out_of_scope_routes(): void
    {
        $this->seed();
        $foreignBranch = Branch::create(['code' => 'CANCEL-03', 'name' => 'Sucursal ajena']);
        $foreignCampaign = Campaign::create(['code' => 'CANCEL-FOREIGN', 'name' => 'Campaña ajena', 'status' => 'open', 'branch_id' => $foreignBranch->id]);
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $superToken = $actor->createToken('angular')->plainTextToken;
        $completedRoute = DeliveryRoute::create(['campaign_id' => $foreignCampaign->id, 'branch_id' => $foreignBranch->id, 'code' => 'RUTA-COMPLETED', 'name' => 'Ruta completada', 'status' => 'completed']);
        $this->assertTrue($actor->hasRole('super_admin'));
        $this->assertDatabaseHas('delivery_routes', ['id' => $completedRoute->id, 'status' => 'completed']);
        $this->withToken($superToken)->patchJson('/api/v1/routes/'.$completedRoute->id.'/status', ['status' => 'cancelled'])
            ->assertStatus(422);
    }

    public function test_campaign_manager_cannot_cancel_route_outside_scope(): void
    {
        $this->seed();
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'campaign_manager')->firstOrFail());
        $managedBranch = Branch::create(['code' => 'CANCEL-04', 'name' => 'Sucursal del admin']);
        $foreignBranch = Branch::create(['code' => 'CANCEL-05', 'name' => 'Sucursal ajena']);
        $admin->branches()->attach($managedBranch);
        $foreignCampaign = Campaign::create(['code' => 'CANCEL-FOREIGN-2', 'name' => 'Campana ajena 2', 'status' => 'open', 'branch_id' => $foreignBranch->id]);
        $route = DeliveryRoute::create(['campaign_id' => $foreignCampaign->id, 'branch_id' => $foreignBranch->id, 'code' => 'RUTA-FOREIGN', 'name' => 'Ruta ajena', 'status' => 'planned']);
        $token = $admin->createToken('angular')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/routes/'.$route->id.'/status', ['status' => 'cancelled'])->assertNotFound();
    }
}
