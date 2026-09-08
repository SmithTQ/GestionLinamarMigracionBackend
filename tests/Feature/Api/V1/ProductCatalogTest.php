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

        $category = $this->withToken($token)->postJson('/api/v1/product-categories', ['name'=>'Flores','slug'=>'flores'])->assertCreated()->json('datos');
        $subcategory = $this->withToken($token)->postJson('/api/v1/product-subcategories', ['category_id'=>$category['id'],'name'=>'Ramos','slug'=>'ramos'])->assertCreated()->json('datos');
        $product = $this->withToken($token)->postJson('/api/v1/products', ['subcategory_id'=>$subcategory['id'],'sku'=>'RAMO-001','name'=>'Ramo clásico','slug'=>'ramo-clasico','base_price'=>59.90])->assertCreated()->json('datos');

        $branch = Branch::create(['code'=>'LIMA-01','name'=>'Sucursal Lima']);
        $campaign = $this->withToken($token)->postJson('/api/v1/campaigns', ['code'=>'CAMP-PRODUCT-01','name'=>'Campaña productos','status'=>'open','branch_ids'=>[$branch->id]])->assertCreated()->json('datos');
        $this->withToken($token)->putJson('/api/v1/campaigns/'.$campaign['id'].'/products', ['products'=>[['product_id'=>$product['id'],'price'=>64.90]]])->assertOk()->assertJsonPath('datos.0.campaign_pivot.price','64.90');

        $this->withToken($token)->postJson('/api/v1/orders', ['campaign_id'=>$campaign['id'],'branch_id'=>$branch->id,'product_id'=>$product['id'],'sender_name'=>'Ana','sender_phone'=>'987654321','recipient_name'=>'Luis','recipient_phone'=>'966554433','district'=>'Miraflores','address'=>'Av. Test 100'])->assertCreated()->assertJsonPath('datos.product_name','Ramo clásico')->assertJsonPath('datos.product_price','64.90');
    }
}
