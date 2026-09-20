<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\Customer;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\FormInvitation;
use App\Models\FormTemplate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_returns_customer_prefill_without_sensitive_data(): void
    {
        $this->seed();
        $invitation = $this->createInvitation('prefill-token');

        $response = $this->getJson('/api/v1/public/invitations/'.$invitation->token)->assertOk();
        $response->assertJsonPath('datos.prefill.sender_name', 'Cliente Precargado');
        $response->assertJsonPath('datos.prefill.sender_phone', '51999999999');
        $response->assertJsonMissingPath('datos.prefill.email');
        $response->assertJsonMissingPath('datos.prefill.notes');
        $response->assertJsonMissingPath('datos.prefill.id');
        $response->assertJsonMissingPath('datos.customer');
    }

    public function test_used_invitation_returns_not_found(): void
    {
        $this->seed();
        $invitation = $this->createInvitation('used-prefill-token');
        $invitation->update(['status' => 'used', 'used_at' => now()]);

        $this->getJson('/api/v1/public/invitations/'.$invitation->token)->assertNotFound();
    }

    public function test_expired_invitation_returns_not_found(): void
    {
        $this->seed();
        $invitation = $this->createInvitation('expired-prefill-token');
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/public/invitations/'.$invitation->token)->assertNotFound();
    }

    private function createInvitation(string $token): FormInvitation
    {
        $branch = Branch::create(['code' => 'PREFILL-BRANCH', 'name' => 'Sucursal prefill']);
        $campaign = Campaign::create(['code' => 'PREFILL-CAMP-'.$token, 'name' => 'Campana prefill', 'status' => 'open', 'branch_id' => $branch->id]);
        $district = District::create(['code' => 'PREFILL-DIST-'.$token, 'name' => 'Miraflores']);
        $districtList = DistrictList::create(['branch_id' => $branch->id, 'code' => 'PREFILL-LIST-'.$token, 'name' => 'Cobertura prefill', 'is_active' => true]);
        $districtList->districts()->attach($district);
        $campaign->districtLists()->attach($districtList);
        $product = Product::create(['branch_id' => $branch->id, 'sku' => 'PREFILL-'.$token, 'name' => 'Producto prefill', 'slug' => 'producto-'.$token, 'base_price' => 10]);
        $campaign->products()->attach($product, ['price' => 10, 'is_available' => true]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'public-'.$token, 'title' => 'Formulario prefill', 'status' => 'published', 'published_at' => now()]);
        $form->fields()->sync($template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all());
        $customer = Customer::create(['full_name' => 'Cliente Precargado', 'whatsapp_number' => '51999999999', 'email' => 'privado@example.test', 'notes' => 'Dato privado', 'is_active' => true]);

        return FormInvitation::create(['campaign_form_id' => $form->id, 'customer_id' => $customer->id, 'token' => $token, 'status' => 'pending']);
    }
}
