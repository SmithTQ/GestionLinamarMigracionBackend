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

class InternalCampaignFormAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_manager_can_read_and_submit_an_internal_form_from_an_assigned_branch(): void
    {
        [$form, $campaign, $product, $district] = $this->formFixture();
        $manager = $this->userWithRole('campaign_manager');
        $campaign->branch->users()->attach($manager);

        $this->actingAs($manager)
            ->getJson('/api/v1/internal/forms/'.$form->public_key)
            ->assertOk();

        $this->actingAs($manager)
            ->postJson('/api/v1/internal/forms/'.$form->public_key.'/submissions', $this->payload($product, $district, 'manager-submission'))
            ->assertCreated();
    }

    public function test_dispatcher_can_read_and_submit_an_assigned_internal_form(): void
    {
        [$form, $campaign, $product, $district] = $this->formFixture();
        $dispatcher = $this->userWithRole('dispatcher');
        $campaign->users()->attach($dispatcher, ['is_active' => true]);

        $this->actingAs($dispatcher)
            ->getJson('/api/v1/internal/forms/'.$form->public_key)
            ->assertOk();

        $this->actingAs($dispatcher)
            ->postJson('/api/v1/internal/forms/'.$form->public_key.'/submissions', $this->payload($product, $district, 'dispatcher-submission'))
            ->assertCreated();
    }

    public function test_manager_from_another_branch_is_forbidden(): void
    {
        [$form, $campaign] = $this->formFixture();
        $manager = $this->userWithRole('campaign_manager');
        $otherBranch = Branch::create(['code' => 'OTHER-BRANCH', 'name' => 'Otra sucursal']);
        $otherBranch->users()->attach($manager);

        $this->actingAs($manager)
            ->getJson('/api/v1/internal/forms/'.$form->public_key)
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No tienes acceso a esta campaña.');
    }

    public function test_unassigned_dispatcher_is_forbidden(): void
    {
        [$form, $campaign] = $this->formFixture();
        $dispatcher = $this->userWithRole('dispatcher');

        $this->actingAs($dispatcher)
            ->getJson('/api/v1/internal/forms/'.$form->public_key)
            ->assertForbidden();
    }

    public function test_super_admin_can_read_and_submit_any_internal_form(): void
    {
        [$form, $campaign, $product, $district] = $this->formFixture();
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)
            ->getJson('/api/v1/internal/forms/'.$form->public_key)
            ->assertOk();

        $this->actingAs($admin)
            ->postJson('/api/v1/internal/forms/'.$form->public_key.'/submissions', $this->payload($product, $district, 'admin-submission'))
            ->assertCreated();
    }

    public function test_invitation_name_is_optional_and_sender_name_updates_the_customer(): void
    {
        [$form, $campaign, $product, $district] = $this->formFixture();
        $admin = $this->userWithRole('super_admin');

        $invitation = $this->actingAs($admin)
            ->postJson('/api/v1/customer-invitations', [
                'form_id' => $form->id,
                'whatsapp_number' => '51999111222',
            ])
            ->assertCreated()
            ->json('datos');

        $this->assertSame('', $invitation['customer']['full_name']);

        $this->postJson('/api/v1/public/invitations/'.$invitation['invitation_token'].'/submissions', $this->payload($product, $district, 'invitation-submission', 'Nombre capturado en formulario'))
            ->assertCreated();

        $this->assertDatabaseHas('customers', [
            'whatsapp_number' => '51999111222',
            'full_name' => 'Nombre capturado en formulario',
        ]);
    }

    private function formFixture(): array
    {
        $this->seed();
        $branch = Branch::create(['code' => 'FORM-01', 'name' => 'Sucursal formulario']);
        $campaign = Campaign::create(['code' => 'FORM-CAMP', 'name' => 'Campana formulario', 'status' => 'open', 'branch_id' => $branch->id]);
        $district = District::create(['code' => 'FORM-DIST', 'name' => 'Distrito formulario']);
        $districtList = DistrictList::create(['branch_id' => $branch->id, 'code' => 'FORM-LIST', 'name' => 'Cobertura formulario', 'is_active' => true]);
        $districtList->districts()->attach($district);
        $campaign->districtLists()->attach($districtList);
        $product = Product::create(['branch_id' => $branch->id, 'sku' => 'FORM-001', 'name' => 'Producto formulario', 'slug' => 'producto-formulario', 'base_price' => 10]);
        $campaign->products()->attach($product, ['price' => 10, 'is_available' => true]);
        $template = FormTemplate::with('fields')->firstOrFail();
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'form-'.$campaign->id, 'title' => 'Formulario interno', 'status' => 'published', 'published_at' => now()]);
        $form->fields()->sync($template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all());

        return [$form, $campaign, $product, $district];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function payload(Product $product, District $district, string $submissionKey, string $senderName = 'Remitente de prueba'): array
    {
        return [
            'submission_key' => $submissionKey,
            'product_sku' => $product->sku,
            'sender_name' => $senderName,
            'sender_phone' => '51999111222',
            'recipient_name' => 'Destinatario de prueba',
            'recipient_phone' => '51999333444',
            'district_code' => $district->code,
            'delivery_reference' => 'Casa azul frente al parque',
            'latitude' => -12.02,
            'longitude' => -77.03,
            'delivery_time' => '10:00',
        ];
    }
}
