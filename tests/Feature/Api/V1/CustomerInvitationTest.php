<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\FormTemplate;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_generates_whatsapp_link_and_can_be_used_once(): void
    {
        $this->seed();
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $auth = $admin->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'INV-01', 'name' => 'Sucursal invitación']);
        $campaign = Campaign::create(['code' => 'INV-CAMP', 'name' => 'Campaña invitación', 'status' => 'open']);
        $campaign->branches()->attach($branch);
        $district = District::create(['code' => 'LIM-INV', 'name' => 'Miraflores']);
        $districtList = DistrictList::create(['code' => 'INV-LIST', 'name' => 'Cobertura invitación', 'is_active' => true]);
        $districtList->districts()->attach($district);
        $campaign->districtLists()->attach($districtList);
        $product = Product::create(['sku' => 'INV-001', 'name' => 'Producto invitación', 'slug' => 'producto-invitacion', 'base_price' => 10]);
        $campaign->products()->attach($product, ['price' => 10, 'is_available' => true]);
        $template = FormTemplate::with('fields')->firstOrFail();
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'inv-form-key', 'title' => 'Formulario', 'status' => 'published', 'published_at' => now()]);
        $form->fields()->sync($template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all());
        $invitation = $this->withToken($auth)->postJson('/api/v1/customer-invitations', ['form_id' => $form->id, 'full_name' => 'Cliente Uno', 'whatsapp_number' => '+51 987 654 321'])->assertCreated()->assertJsonPath('datos.customer.whatsapp_number', '51987654321')->json('datos');
        $this->assertStringContainsString('wa.me/51987654321', $invitation['whatsapp_url']);
        $payload = ['submission_key' => 'inv-sub-1', 'product_sku' => 'INV-001', 'sender_name' => 'Cliente Uno', 'sender_phone' => '51987654321', 'recipient_name' => 'Destinatario', 'recipient_phone' => '966554433', 'district_code' => 'LIM-INV', 'address' => 'Av. Test 1', 'latitude' => -12.12, 'longitude' => -77.03, 'delivery_time' => '10:00'];
        $this->postJson('/api/v1/public/invitations/'.$invitation['invitation_token'].'/submissions', $payload)->assertCreated();
        $this->postJson('/api/v1/public/invitations/'.$invitation['invitation_token'].'/submissions', $payload)->assertStatus(404);
        $this->assertDatabaseHas('orders', ['customer_id' => $invitation['customer']['id']]);
    }
}
