<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\FormField;
use App\Models\FormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());

        return $user->createToken('template-tests')->plainTextToken;
    }

    public function test_template_and_custom_field_can_be_managed(): void
    {
        $this->seed();
        $token = $this->token();

        $template = $this->withToken($token)->postJson('/api/v1/form-templates', [
            'code' => 'campaign-order-v2',
            'name' => 'Pedidos avanzados',
            'description' => 'Campos adicionales',
        ])->assertCreated()->json('datos');

        $field = $this->withToken($token)->postJson('/api/v1/form-templates/'.$template['id'].'/fields', [
            'key' => 'invoice_number',
            'label' => 'Número de factura',
            'type' => 'text',
            'validation_rules' => ['max_length' => 50],
            'sort_order' => 120,
        ])->assertCreated()->assertJsonPath('datos.is_system', false)->json('datos');

        $this->withToken($token)->patchJson('/api/v1/form-templates/'.$template['id'].'/fields/'.$field['id'], [
            'label' => 'Referencia',
            'sort_order' => 130,
        ])->assertOk()->assertJsonPath('datos.label', 'Referencia');

        $this->withToken($token)->deleteJson('/api/v1/form-templates/'.$template['id'].'/fields/'.$field['id'])
            ->assertOk()
            ->assertJsonPath('datos.is_active', false);

        $this->withToken($token)->deleteJson('/api/v1/form-templates/'.$template['id'])
            ->assertOk()
            ->assertJsonPath('datos.is_active', false);
    }

    public function test_system_fields_cannot_be_deleted(): void
    {
        $this->seed();
        $token = $this->token();
        $template = FormTemplate::where('code', 'campaign-order-v1')->firstOrFail();
        $field = $template->fields()->where('key', 'product')->firstOrFail();

        $this->withToken($token)->deleteJson('/api/v1/form-templates/'.$template->id.'/fields/'.$field->id)
            ->assertStatus(422);
    }

    public function test_form_rejects_a_field_from_another_template(): void
    {
        $this->seed();
        $token = $this->token();
        $branch = Branch::create(['code' => 'CENTRAL', 'name' => 'Sucursal interna']);
        $campaign = Campaign::create(['code' => 'CAMP-TEMPLATE-TEST', 'name' => 'Campana plantilla', 'status' => 'open']);
        $campaign->branches()->attach($branch);
        $template = FormTemplate::where('code', 'campaign-order-v1')->firstOrFail();
        $other = FormTemplate::create(['code' => 'other-template', 'name' => 'Otra plantilla', 'is_active' => true]);
        $foreignField = FormField::create(['template_id' => $other->id, 'key' => 'foreign_field', 'label' => 'Campo externo', 'type' => 'text', 'is_system' => false, 'is_active' => true, 'sort_order' => 10]);

        $this->withToken($token)->postJson('/api/v1/campaign-forms', [
            'campaign_id' => $campaign->id,
            'branch_id' => $branch->id,
            'template_id' => $template->id,
            'title' => 'Formulario inválido',
            'fields' => [['field_id' => $foreignField->id, 'is_enabled' => true, 'is_required' => false, 'sort_order' => 10]],
        ])->assertStatus(422);
    }
}
