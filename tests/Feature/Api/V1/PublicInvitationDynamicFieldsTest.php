<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\Customer;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\FormField;
use App\Models\FormInvitation;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_submission_persists_custom_fields(): void
    {
        $this->seed();
        $branch = Branch::create(['code' => 'INV-DYNAMIC', 'name' => 'Sucursal invitacion']);
        $campaign = Campaign::create(['code' => 'INV-DYNAMIC-CAMP', 'name' => 'Campana invitacion', 'status' => 'open']);
        $campaign->branches()->attach($branch);
        $district = District::create(['code' => 'INV-DYNAMIC-DIST', 'name' => 'Miraflores']);
        $districtList = DistrictList::create(['code' => 'INV-DYNAMIC-LIST', 'name' => 'Cobertura invitacion', 'is_active' => true]);
        $districtList->districts()->attach($district);
        $campaign->districtLists()->attach($districtList);
        $product = Product::create(['sku' => 'INV-DYNAMIC-001', 'name' => 'Producto invitacion', 'slug' => 'producto-invitacion-dynamic', 'base_price' => 10]);
        $campaign->products()->attach($product, ['price' => 10, 'is_available' => true]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $reference = FormField::create(['template_id' => $template->id, 'key' => 'reference', 'label' => 'Referencia', 'type' => 'text', 'is_system' => false, 'is_active' => true, 'sort_order' => 120]);
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'inv-dynamic-form', 'title' => 'Formulario invitacion', 'status' => 'published', 'published_at' => now()]);
        $fields = $template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all();
        $fields[$reference->id] = ['is_enabled' => true, 'is_required' => true, 'sort_order' => 120];
        $form->fields()->sync($fields);
        $customer = Customer::create(['full_name' => 'Cliente invitado', 'whatsapp_number' => '51999999999', 'is_active' => true]);
        $invitation = FormInvitation::create(['campaign_form_id' => $form->id, 'customer_id' => $customer->id, 'token' => 'inv-dynamic-token', 'status' => 'pending']);

        $payload = ['submission_key' => 'inv-dynamic-submission', 'product_sku' => $product->sku, 'sender_name' => 'Cliente', 'sender_phone' => '51999999999', 'recipient_name' => 'Destinatario', 'recipient_phone' => '51988888888', 'district_code' => $district->code, 'latitude' => -12.12, 'longitude' => -77.03, 'delivery_time' => '10:00', 'reference' => 'Puerta azul'];

        $this->postJson('/api/v1/public/invitations/'.$invitation->token.'/submissions', $payload)->assertCreated();

        $submission = FormSubmission::where('submission_key', $payload['submission_key'])->firstOrFail();
        $this->assertSame('Puerta azul', $submission->payload['reference']);
        $this->assertDatabaseHas('orders', ['product_id' => $product->id, 'recipient_name' => 'Destinatario']);
    }
}
