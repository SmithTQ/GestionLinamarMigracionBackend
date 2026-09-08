<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_google_credentials_returns_safe_failure_and_records_import(): void
    {
        $this->seed();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail());
        $token = $actor->createToken('angular')->plainTextToken;
        $branch = Branch::create(['code' => 'LIMA-01', 'name' => 'Sucursal Lima']);
        $campaign = Campaign::create(['code' => 'CAMP-01', 'name' => 'Campaña 01', 'status' => 'open']);
        $campaign->branches()->attach($branch);

        $this->withToken($token)
            ->postJson('/api/v1/imports/google-sheets', [
                'campaign_id' => $campaign->id,
                'branch_id' => $branch->id,
                'spreadsheet_id' => 'spreadsheet-test',
                'range' => 'Pedidos!A2:Z',
            ])
            ->assertStatus(502)
            ->assertJsonPath('mensaje', 'No se pudo leer la fuente de Google Sheets.');

        $this->assertDatabaseHas('imports', ['status' => 'failed', 'source' => 'google_sheets']);
    }
}
