<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\District;
use App\Models\FormTemplate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCampaignFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_returns_catalogs_and_creates_idempotent_order(): void
    {
        $this->seed();
        $branch=Branch::create(['code'=>'PUBLIC-01','name'=>'Sucursal pública']);
        $campaign=Campaign::create(['code'=>'PUBLIC-CAMP','name'=>'Campaña pública','status'=>'open']); $campaign->branches()->attach($branch);
        $district=District::create(['code'=>'LIM-MIR','name'=>'Miraflores']);
        $product=Product::create(['sku'=>'PUB-001','name'=>'Producto público','slug'=>'producto-publico','base_price'=>20]); $campaign->districts()->attach($district); $campaign->products()->attach($product,['price'=>25,'is_available'=>true]);
        $template=FormTemplate::with('fields')->where('code','campaign-order-v1')->firstOrFail();
        $form=CampaignForm::create(['campaign_id'=>$campaign->id,'branch_id'=>$branch->id,'template_id'=>$template->id,'public_key'=>'public-test-key','title'=>'Formulario público','status'=>'published','published_at'=>now()]);
        $form->fields()->sync($template->fields->mapWithKeys(fn($field)=>[$field->id=>['is_enabled'=>true,'is_required'=>false,'sort_order'=>$field->sort_order]])->all());

        $this->getJson('/api/v1/public/forms/public-test-key')->assertOk()->assertJsonPath('datos.products.0.sku','PUB-001')->assertJsonPath('datos.districts.0.code','LIM-MIR');
        $payload=['submission_key'=>'submission-001','product_sku'=>'PUB-001','sender_name'=>'Ana','sender_phone'=>'987654321','recipient_name'=>'Luis','recipient_phone'=>'966554433','district_code'=>'LIM-MIR','address'=>'Av. Test 100','latitude'=>-12.12,'longitude'=>-77.03];
        $this->postJson('/api/v1/public/forms/public-test-key/submissions',$payload)->assertCreated()->assertJsonStructure(['datos'=>['submission_key','order_id']]);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions',$payload)->assertOk()->assertJsonPath('datos.submission_key','submission-001');
        $this->assertDatabaseCount('orders',1);
    }
}
