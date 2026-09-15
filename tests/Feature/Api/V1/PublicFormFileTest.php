<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\FormSubmissionFile;
use App\Models\FormTemplate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFormFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_image_is_stored_as_private_submission_metadata(): void
    {
        Storage::fake('local');
        $this->seed();
        $branch = Branch::create(['code' => 'FILE-01', 'name' => 'Sucursal archivos']);
        $campaign = Campaign::create(['code' => 'FILE-CAMP', 'name' => 'Campana archivos', 'status' => 'open']);
        $campaign->branches()->attach($branch);
        $district = District::create(['code' => 'FILE-DIST', 'name' => 'Miraflores']);
        $list = DistrictList::create(['code' => 'FILE-LIST', 'name' => 'Cobertura archivos', 'is_active' => true]);
        $list->districts()->attach($district);
        $campaign->districtLists()->attach($list);
        $product = Product::create(['sku' => 'FILE-001', 'name' => 'Producto archivo', 'slug' => 'producto-archivo', 'base_price' => 10]);
        $campaign->products()->attach($product, ['price' => 10, 'is_available' => true]);
        $template = FormTemplate::with('fields')->where('code', 'campaign-order-v1')->firstOrFail();
        $form = CampaignForm::create(['campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'file-form-key', 'title' => 'Formulario archivos', 'status' => 'published', 'published_at' => now()]);
        $form->fields()->sync($template->fields->mapWithKeys(fn ($field) => [$field->id => ['is_enabled' => true, 'is_required' => false, 'sort_order' => $field->sort_order]])->all());

        $response = $this->post('/api/v1/public/forms/file-form-key/submissions', [
            'submission_key' => 'file-submission-1', 'product_sku' => $product->sku,
            'sender_name' => 'Ana', 'sender_phone' => '987654321', 'recipient_name' => 'Luis',
            'recipient_phone' => '966554433', 'district_code' => $district->code,
            'latitude' => -12.12, 'longitude' => -77.03,
            'photo' => UploadedFile::fake()->image('entrega.jpg'),
        ]);

        $response->assertCreated();
        $file = FormSubmissionFile::firstOrFail();
        $this->assertSame('image/jpeg', $file->mime_type);
        $this->assertStringNotContainsString('photo', json_encode($file->submission->payload));
        Storage::disk('local')->assertExists($file->path);
    }
}
