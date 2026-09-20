<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\FormField;
use App\Models\FormSubmission;
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
        $branch = Branch::create(['code' => 'PUBLIC-01', 'name' => 'Sucursal publica']);
        $campaign = Campaign::create(['code' => 'PUBLIC-CAMP', 'name' => 'Campana publica', 'status' => 'open', 'branch_id' => $branch->id]);
        $district = District::create(['code' => 'LIM-MIR', 'name' => 'Miraflores']);
        $districtList = DistrictList::create(['branch_id' => $branch->id, 'code' => 'PUBLIC-LIST', 'name' => 'Cobertura publica', 'is_active' => true]);
        $districtList->districts()->attach($district);
        $campaign->districtLists()->attach($districtList);
        $product = Product::create(['branch_id' => $branch->id, 'sku' => 'PUB-001', 'name' => 'Producto publico', 'slug' => 'producto-publico', 'base_price' => 20]);
        $campaign->products()->attach($product, ['price' => 25, 'is_available' => true]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $invoiceField = FormField::create(['template_id' => $template->id, 'key' => 'invoice_number', 'label' => 'Numero de factura', 'type' => 'text', 'is_system' => false, 'is_active' => true, 'validation_rules' => ['max_length' => 50], 'sort_order' => 120]);
        $shiftField = FormField::create(['template_id' => $template->id, 'key' => 'delivery_shift', 'label' => 'Turno de entrega', 'type' => 'select', 'is_system' => false, 'is_active' => true, 'sort_order' => 130]);
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'public-test-key', 'title' => 'Formulario publico', 'status' => 'published', 'published_at' => now()]);
        $formFields = $template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all();
        $formFields[$invoiceField->id] = ['is_enabled' => true, 'is_required' => true, 'sort_order' => 120];
        $formFields[$shiftField->id] = ['is_enabled' => true, 'is_required' => true, 'config' => json_encode(['options' => ['morning', 'afternoon']]), 'sort_order' => 130];
        $form->fields()->sync($formFields);

        $this->getJson('/api/v1/public/forms/public-test-key')->assertOk()->assertJsonPath('datos.products.0.sku', 'PUB-001')->assertJsonPath('datos.products.0.image_url', null)->assertJsonPath('datos.products.0.image_thumbnail_url', null)->assertJsonPath('datos.districts.0.code', 'LIM-MIR');
        $payload = ['submission_key' => 'submission-001', 'product_sku' => 'PUB-001', 'sender_name' => 'Ana', 'sender_phone' => '987654321', 'recipient_name' => 'Luis', 'recipient_phone' => '966554433', 'district_code' => 'LIM-MIR', 'latitude' => -12.12, 'longitude' => -77.03, 'delivery_reference' => 'Casa azul frente al parque', 'delivery_date' => '31/12/2026', 'delivery_time' => 'Por la tarde', 'invoice_number' => 'F001-123', 'delivery_shift' => 'afternoon', 'comments' => 'Campo desconocido no enviado'];
        unset($payload['comments']);
        $missingReferencePayload = $payload;
        unset($missingReferencePayload['delivery_reference']);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions', [...$missingReferencePayload, 'submission_key' => 'missing-reference'])->assertStatus(422);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions', $payload)->assertCreated()->assertJsonStructure(['datos' => ['submission_key', 'order_id']]);
        $submission = FormSubmission::where('submission_key', 'submission-001')->firstOrFail();
        $this->assertSame('submission-001', $submission->payload['submission_key']);
        $this->assertSame('2026-12-31', $submission->payload['delivery_date']);
        $this->assertSame('F001-123', $submission->payload['invoice_number']);
        $this->assertSame('afternoon', $submission->payload['delivery_shift']);
        $this->assertDatabaseHas('orders', ['campaign_id' => $campaign->id, 'delivery_reference' => 'Casa azul frente al parque']);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions', $payload)->assertOk()->assertJsonPath('datos.submission_key', 'submission-001');
        $this->assertDatabaseCount('orders', 1);

        $this->postJson('/api/v1/public/forms/public-test-key/submissions', [...$payload, 'submission_key' => 'missing-required', 'invoice_number' => ''])->assertStatus(422);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions', [...$payload, 'submission_key' => 'invalid-option', 'delivery_shift' => 'night'])->assertStatus(422);
        $this->postJson('/api/v1/public/forms/public-test-key/submissions', [...$payload, 'submission_key' => 'unknown-field', 'unknown_field' => 'x'])->assertStatus(422);
    }
}
