<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\FormField;
use App\Models\FormTemplate;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_form_can_be_configured_and_published(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-FORM', 'name' => 'Sucursal Formulario']);
        $campaign = Campaign::create(['code' => 'CAMP-FORM-01', 'name' => 'Campaña formulario', 'status' => 'open', 'branch_id' => $branch->id]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $fields = $template->fields->map(fn ($field) => ['field_id' => $field->id, 'is_enabled' => true, 'is_required' => $field->key !== 'dedication', 'sort_order' => $field->sort_order])->values()->all();

        $form = $this->withToken($token)->postJson('/api/v1/campaign-forms', ['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'title' => 'Pedidos de campaña', 'fields' => $fields])->assertCreated()->assertJsonPath('datos.status', 'draft')->json('datos');
        $this->withToken($token)->postJson('/api/v1/campaign-forms/'.$form['id'].'/publish')->assertOk()->assertJsonPath('datos.status', 'published');
    }

    public function test_campaign_form_uses_default_branch_when_branch_is_omitted(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'CENTRAL', 'name' => 'Sucursal interna']);
        $campaign = Campaign::create(['code' => 'CAMP-FORM-DEFAULT', 'name' => 'Campana formulario', 'status' => 'open', 'branch_id' => $branch->id]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $requiredKeys = ['product', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'district', 'location', 'delivery_reference', 'delivery_time'];
        $fields = $template->fields->map(fn ($field) => ['field_id' => $field->id, 'is_enabled' => true, 'is_required' => in_array($field->key, $requiredKeys, true), 'sort_order' => $field->sort_order])->values()->all();

        $this->withToken($token)
            ->postJson('/api/v1/campaign-forms', ['campaign_id' => $campaign->id, 'template_id' => $template->id, 'title' => 'Formulario interno', 'fields' => $fields])
            ->assertCreated()
            ->assertJsonPath('datos.branch_id', $branch->id);
    }

    public function test_delivery_reference_cannot_be_disabled_or_made_optional(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'REF-BRANCH', 'name' => 'Sucursal referencia']);
        $campaign = Campaign::create(['code' => 'CAMP-REF-01', 'name' => 'Campana referencia', 'status' => 'open', 'branch_id' => $branch->id]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $fields = $template->fields->map(fn ($field) => [
            'field_id' => $field->id,
            'is_enabled' => $field->key !== 'delivery_reference',
            'is_required' => false,
            'sort_order' => $field->sort_order,
        ])->values()->all();

        $this->withToken($token)
            ->postJson('/api/v1/campaign-forms', ['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'title' => 'Formulario referencia', 'fields' => $fields])
            ->assertStatus(422);
    }

    public function test_campaign_configuration_saves_form_and_products_atomically(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'CENTRAL', 'name' => 'Sucursal interna']);
        $campaign = Campaign::create(['code' => 'CAMP-CONFIG-01', 'name' => 'Campana configuracion', 'status' => 'open', 'branch_id' => $branch->id]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $requiredKeys = ['product', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'district', 'location', 'delivery_reference', 'delivery_time'];
        $fields = $template->fields->map(fn ($field) => ['field_id' => $field->id, 'is_enabled' => true, 'is_required' => in_array($field->key, $requiredKeys, true), 'sort_order' => $field->sort_order])->values()->all();
        $product = Product::create(['branch_id' => $branch->id, 'sku' => 'CONFIG-001', 'name' => 'Producto configuración', 'slug' => 'producto-configuracion', 'base_price' => 25]);

        $this->withToken($token)->putJson('/api/v1/campaigns/'.$campaign->id.'/configuration', [
            'form' => ['template_id' => $template->id, 'title' => 'Formulario configurado', 'fields' => $fields],
            'products' => [['product_id' => $product->id, 'price' => 30, 'sort_order' => 1]],
        ])->assertOk()->assertJsonPath('datos.form.title', 'Formulario configurado')->assertJsonPath('datos.products.0.id', $product->id);

        $this->assertDatabaseHas('campaign_forms', ['campaign_id' => $campaign->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('campaign_product', ['campaign_id' => $campaign->id, 'product_id' => $product->id]);
    }

    public function test_campaign_configuration_rolls_back_when_form_fields_are_invalid(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $campaign = Campaign::create(['code' => 'CAMP-CONFIG-ROLLBACK', 'name' => 'Campana rollback', 'status' => 'open', 'branch_id' => Branch::create(['code' => 'ROLLBACK-BRANCH', 'name' => 'Sucursal rollback'])->id]);
        $template = FormTemplate::where('code', 'campaign-order-v1')->firstOrFail();
        $other = FormTemplate::create(['code' => 'foreign-template', 'name' => 'Plantilla externa', 'is_active' => true]);
        $foreignField = FormField::create(['template_id' => $other->id, 'key' => 'foreign_field', 'label' => 'Campo externo', 'type' => 'text', 'is_system' => false, 'is_active' => true, 'sort_order' => 10]);
        $product = Product::create(['branch_id' => $campaign->branch_id, 'sku' => 'ROLLBACK-001', 'name' => 'Producto rollback', 'slug' => 'producto-rollback', 'base_price' => 25]);

        $this->withToken($token)->putJson('/api/v1/campaigns/'.$campaign->id.'/configuration', [
            'form' => ['template_id' => $template->id, 'title' => 'Debe revertirse', 'fields' => [['field_id' => $foreignField->id]]],
            'products' => [['product_id' => $product->id]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('campaign_forms', ['campaign_id' => $campaign->id]);
        $this->assertDatabaseMissing('campaign_product', ['campaign_id' => $campaign->id, 'product_id' => $product->id]);
    }
}
