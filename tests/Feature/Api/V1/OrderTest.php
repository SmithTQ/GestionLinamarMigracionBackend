<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderDeliveryEvidence;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_orders_expose_and_download_delivery_evidence_with_scope(): void
    {
        Storage::fake('local');
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'ORDER-EVIDENCE', 'name' => 'Sucursal pedidos']);
        $campaign = Campaign::create(['code' => 'ORDER-EVIDENCE-CAMP', 'name' => 'Campana pedidos', 'status' => 'open', 'branch_id' => $branch->id]);
        $order = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Luis',
            'recipient_phone' => '999000222', 'district' => 'Miraflores', 'address' => 'Av. Lima 1',
            'delivery_reference' => 'Casa azul', 'status' => 'delivered',
        ]);
        $courier = Courier::create(['name' => 'Motorizado interno', 'phone' => '51999999991']);
        $route = DeliveryRoute::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'courier_id' => $courier->id, 'code' => 'RUTA-ORDER-EVIDENCE', 'name' => 'Ruta pedidos', 'status' => 'completed']);
        $path = 'delivery-evidence/'.$route->id.'/delivery.jpg';
        Storage::disk('local')->put($path, 'fake-image');
        $evidence = OrderDeliveryEvidence::create([
            'order_id' => $order->id, 'delivery_route_id' => $route->id, 'delivered_by_courier_id' => $courier->id,
            'disk' => 'local', 'path' => $path, 'mime_type' => 'image/jpeg', 'size' => 10, 'delivered_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/orders?campaign_id='.$campaign->id)
            ->assertOk()
            ->assertJsonPath('datos.0.delivery_evidence.id', $evidence->id)
            ->assertJsonPath('datos.0.delivery_evidence.delivered_by_courier_name', 'Motorizado interno')
            ->assertJsonMissingPath('datos.0.delivery_evidence.path')
            ->assertJsonMissingPath('datos.0.delivery_evidence.disk');

        $this->withToken($token)
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('datos.delivery_evidence.id', $evidence->id);

        $download = $this->withToken($token)->get('/api/v1/orders/'.$order->id.'/delivery-evidence')->assertOk();
        $download->assertHeader('Content-Type', 'image/jpeg');
        $download->assertHeader('Cache-Control', 'max-age=300, private');
        $this->assertSame('fake-image', $download->streamedContent());

        $orderWithoutEvidence = Order::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'product_name' => 'Ramo 2',
            'sender_name' => 'Ana', 'sender_phone' => '999000111', 'recipient_name' => 'Maria',
            'recipient_phone' => '999000333', 'district' => 'Surco', 'address' => 'Av. Lima 2',
            'delivery_reference' => 'Casa blanca', 'status' => 'pending',
        ]);
        $this->withToken($token)->getJson('/api/v1/orders/'.$orderWithoutEvidence->id)->assertOk()->assertJsonPath('datos.delivery_evidence', null);
        $this->withToken($token)->get('/api/v1/orders/'.$orderWithoutEvidence->id.'/delivery-evidence')->assertNotFound();

    }

    public function test_order_creation_is_idempotent_and_status_transitions_are_validated(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = Campaign::create(['code' => 'CAMP-01', 'name' => 'Campaña 01', 'status' => 'open', 'branch_id' => $branch->id]);

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
            'delivery_reference' => 'Casa azul frente al parque',
            'delivery_date' => '2026-10-01',
            'delivery_time' => '10:00',
        ];

        $first = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertCreated();
        $second = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertOk();

        $this->assertSame($first->json('datos.id'), $second->json('datos.id'));
        $this->assertDatabaseCount('orders', 1);

        $this->withToken($token)
            ->patchJson('/api/v1/orders/'.$first->json('datos.id'), ['delivery_date' => '15-11-2026', 'delivery_time' => 'Por la tarde'])
            ->assertOk()
            ->assertJsonPath('datos.delivery_date', '15/11/2026')
            ->assertJsonPath('datos.delivery_date_iso', '2026-11-15')
            ->assertJsonPath('datos.delivery_time', 'Por la tarde')
            ->assertJsonPath('datos.created_at', fn ($value) => preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $value) === 1);

        $this->withToken($token)
            ->getJson('/api/v1/orders?delivery_date=15-11-2026')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1);

        $this->withToken($token)
            ->getJson('/api/v1/orders?delivery_date=31/02/2026')
            ->assertStatus(422);

        $this->withToken($token)
            ->patchJson('/api/v1/orders/'.$first->json('datos.id').'/status', ['status' => 'validated'])
            ->assertOk()
            ->assertJsonPath('datos.status', 'validated');

        $this->withToken($token)
            ->patchJson('/api/v1/orders/'.$first->json('datos.id').'/status', ['status' => 'delivered'])
            ->assertStatus(422);
    }
}
