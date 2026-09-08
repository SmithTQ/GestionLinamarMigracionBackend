<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\FormTemplate;
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
        $user=User::factory()->create(['is_active'=>true]);
        $user->roles()->attach(Role::where('slug','super_admin')->firstOrFail());
        $token=$user->createToken('angular')->plainTextToken;
        $branch=Branch::create(['code'=>'LIMA-FORM','name'=>'Sucursal Formulario']);
        $campaign=Campaign::create(['code'=>'CAMP-FORM-01','name'=>'Campaña formulario','status'=>'open']);
        $campaign->branches()->attach($branch);
        $template=FormTemplate::with('fields')->where('code','campaign-order-v1')->firstOrFail();
        $fields=$template->fields->map(fn($field)=>['field_id'=>$field->id,'is_enabled'=>true,'is_required'=>$field->key !== 'dedication','sort_order'=>$field->sort_order])->values()->all();

        $form=$this->withToken($token)->postJson('/api/v1/campaign-forms',['campaign_id'=>$campaign->id,'branch_id'=>$branch->id,'template_id'=>$template->id,'title'=>'Pedidos de campaña','fields'=>$fields])->assertCreated()->assertJsonPath('datos.status','draft')->json('datos');
        $this->withToken($token)->postJson('/api/v1/campaign-forms/'.$form['id'].'/publish')->assertOk()->assertJsonPath('datos.status','published');
    }
}
