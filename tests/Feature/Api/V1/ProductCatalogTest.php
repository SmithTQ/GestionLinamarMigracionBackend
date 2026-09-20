<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_categorized_assigned_to_campaign_and_used_in_order(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        $category = $this->withToken($token)->postJson('/api/v1/product-categories', ['name' => 'Flores', 'slug' => 'flores'])->assertCreated()->json('datos');
        $subcategory = $this->withToken($token)->postJson('/api/v1/product-subcategories', ['category_id' => $category['id'], 'name' => 'Ramos', 'slug' => 'ramos'])->assertCreated()->json('datos');
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $product = $this->withToken($token)->postJson('/api/v1/products', ['branch_id' => $branch->id, 'subcategory_id' => $subcategory['id'], 'sku' => 'RAMO-001', 'name' => 'Ramo clásico', 'slug' => 'ramo-clasico', 'base_price' => 59.90])->assertCreated()->json('datos');
        $campaign = $this->withToken($token)->postJson('/api/v1/campaigns', ['code' => 'CAMP-PRODUCT-01', 'name' => 'Campaña productos', 'status' => 'open', 'branch_id' => $branch->id])->assertCreated()->json('datos');
        $this->withToken($token)->putJson('/api/v1/campaigns/'.$campaign['id'].'/products', ['products' => [['product_id' => $product['id'], 'price' => 64.90]]])->assertOk()->assertJsonPath('datos.0.campaign_pivot.price', '64.90');

        $this->withToken($token)->postJson('/api/v1/orders', ['campaign_id' => $campaign['id'], 'branch_id' => $branch->id, 'product_id' => $product['id'], 'sender_name' => 'Ana', 'sender_phone' => '987654321', 'recipient_name' => 'Luis', 'recipient_phone' => '966554433', 'district' => 'Miraflores', 'address' => 'Av. Test 100', 'delivery_reference' => 'Casa azul', 'delivery_time' => '14:00 - 16:00'])->assertCreated()->assertJsonPath('datos.product_name', 'Ramo clásico')->assertJsonPath('datos.product_price', '64.90');
    }

    public function test_campaign_products_can_filter_sort_and_return_pivot_data(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        $category = $this->withToken($token)->postJson('/api/v1/product-categories', ['name' => 'Ramos', 'slug' => 'ramos-test'])->assertCreated()->json('datos');
        $subcategory = $this->withToken($token)->postJson('/api/v1/product-subcategories', ['category_id' => $category['id'], 'name' => 'Temporada', 'slug' => 'temporada-test'])->assertCreated()->json('datos');
        $branch = Branch::create(['code' => 'TEST-CATALOG', 'name' => 'Sucursal de prueba']);
        $productA = $this->withToken($token)->postJson('/api/v1/products', ['branch_id' => $branch->id, 'subcategory_id' => $subcategory['id'], 'sku' => 'RAMO-AAA', 'name' => 'Ramo Alfa', 'slug' => 'ramo-alfa', 'base_price' => 20])->assertCreated()->json('datos');
        $productB = $this->withToken($token)->postJson('/api/v1/products', ['branch_id' => $branch->id, 'subcategory_id' => $subcategory['id'], 'sku' => 'RAMO-BBB', 'name' => 'Ramo Beta', 'slug' => 'ramo-beta', 'base_price' => 30])->assertCreated()->json('datos');
        $productC = $this->withToken($token)->postJson('/api/v1/products', ['branch_id' => $branch->id, 'subcategory_id' => $subcategory['id'], 'sku' => 'RAMO-CCC', 'name' => 'Ramo Gamma', 'slug' => 'ramo-gamma', 'base_price' => 40])->assertCreated()->json('datos');
        $campaign = $this->withToken($token)->postJson('/api/v1/campaigns', ['code' => 'CAMP-CATALOG-TEST', 'name' => 'Campana catalogo', 'status' => 'open', 'branch_id' => $branch->id])->assertCreated()->json('datos');

        $this->withToken($token)->putJson('/api/v1/campaigns/'.$campaign['id'].'/products', ['products' => [
            ['product_id' => $productA['id'], 'price' => 25, 'is_available' => false, 'sort_order' => 20, 'max_quantity' => 3],
            ['product_id' => $productB['id'], 'price' => 35, 'is_available' => true, 'sort_order' => 10, 'max_quantity' => 5],
            ['product_id' => $productC['id'], 'price' => 45, 'is_available' => true, 'sort_order' => 30, 'max_quantity' => 7],
        ]])->assertOk()
            ->assertJsonPath('datos.0.campaign_pivot.price', '35.00')
            ->assertJsonPath('datos.0.campaign_pivot.is_available', true)
            ->assertJsonPath('datos.0.campaign_pivot.sort_order', 10)
            ->assertJsonPath('datos.0.campaign_pivot.max_quantity', 5);

        $this->withToken($token)->getJson('/api/v1/campaigns/'.$campaign['id'].'/products?is_available=1&sort_by=name&sort_dir=asc')
            ->assertOk()
            ->assertJsonCount(2, 'datos')
            ->assertJsonPath('datos.0.sku', 'RAMO-BBB')
            ->assertJsonPath('datos.1.sku', 'RAMO-CCC');

        $this->withToken($token)->getJson('/api/v1/campaigns/'.$campaign['id'].'/products?is_available=0&sort_by=name&sort_dir=asc')
            ->assertOk()
            ->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.sku', 'RAMO-AAA');

        $this->withToken($token)->getJson('/api/v1/campaigns/'.$campaign['id'].'/products?sort_by=sku&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('datos.0.sku', 'RAMO-CCC')
            ->assertJsonPath('datos.1.sku', 'RAMO-BBB')
            ->assertJsonCount(2, 'datos');
    }
}
