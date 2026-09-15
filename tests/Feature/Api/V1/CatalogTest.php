<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\FormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_campaign_and_branch(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;

        $branch = $this->withToken($token)->postJson('/api/v1/branches', [
            'code' => 'LIMA-01',
            'name' => 'Sucursal Lima',
        ])->assertCreated()->json('datos');

        $this->withToken($token)->postJson('/api/v1/campaigns', [
            'code' => 'CAMP-2026-01',
            'name' => 'Campaña inicial',
            'status' => 'open',
            'branch_ids' => [$branch['id']],
        ])->assertCreated()
            ->assertJsonPath('datos.branches.0.code', 'LIMA-01');
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('angular')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No tienes permiso para realizar esta operación.');
    }

    public function test_campaign_list_includes_form_summary_without_n_plus_one_queries(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $user->createToken('angular')->plainTextToken;
        $template = FormTemplate::where('code', 'campaign-order-v1')->firstOrFail();
        $branch = Branch::create(['code' => 'SUMMARY-01', 'name' => 'Sucursal resumen']);

        $published = Campaign::create(['code' => 'SUMMARY-PUBLISHED', 'name' => 'Publicada', 'status' => 'open']);
        $published->branches()->attach($branch);
        CampaignForm::create(['campaign_id' => $published->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'summary-published', 'title' => 'Formulario publicado', 'status' => 'published', 'published_at' => now()]);

        $draft = Campaign::create(['code' => 'SUMMARY-DRAFT', 'name' => 'Borrador', 'status' => 'open']);
        $draft->branches()->attach($branch);
        CampaignForm::create(['campaign_id' => $draft->id, 'branch_id' => $branch->id, 'template_id' => $template->id, 'public_key' => 'summary-draft', 'title' => 'Formulario borrador', 'status' => 'draft']);

        $multiple = Campaign::create(['code' => 'SUMMARY-MULTIPLE', 'name' => 'Múltiples', 'status' => 'open']);
        foreach (['closed', 'draft', 'published'] as $index => $status) {
            $formBranch = Branch::create(['code' => 'SUMMARY-0'.($index + 2), 'name' => 'Sucursal '.$status]);
            $multiple->branches()->attach($formBranch);
            CampaignForm::create(['campaign_id' => $multiple->id, 'branch_id' => $formBranch->id, 'template_id' => $template->id, 'public_key' => 'summary-multiple-'.$index, 'title' => 'Formulario '.$status, 'status' => $status, 'published_at' => $status === 'published' ? now() : null]);
        }

        $none = Campaign::create(['code' => 'SUMMARY-NONE', 'name' => 'Sin formulario', 'status' => 'open']);
        $none->branches()->attach($branch);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->withToken($token)->getJson('/api/v1/campaigns?per_page=100')->assertOk();

        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
        $this->assertCampaignSummary($response->json('datos'), 'SUMMARY-PUBLISHED', 'published', true);
        $this->assertCampaignSummary($response->json('datos'), 'SUMMARY-DRAFT', 'draft', false);
        $this->assertCampaignSummary($response->json('datos'), 'SUMMARY-MULTIPLE', 'published', true);
        $this->assertCampaignSummary($response->json('datos'), 'SUMMARY-NONE', null, false);
    }

    private function assertCampaignSummary(array $campaigns, string $code, ?string $formStatus, bool $hasPublishedForm): void
    {
        $campaign = collect($campaigns)->firstWhere('code', $code);

        $this->assertNotNull($campaign);
        $this->assertSame($formStatus, $campaign['form_status']);
        $this->assertSame($hasPublishedForm, $campaign['has_published_form']);
    }
}
