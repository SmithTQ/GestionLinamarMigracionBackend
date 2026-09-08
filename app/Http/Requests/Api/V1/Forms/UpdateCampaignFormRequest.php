<?php
namespace App\Http\Requests\Api\V1\Forms;
use Illuminate\Foundation\Http\FormRequest;
class UpdateCampaignFormRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['title'=>['sometimes','required','string','max:180'],'description'=>['nullable','string','max:5000'],'fields'=>['sometimes','array'],'fields.*.field_id'=>['required','integer','distinct','exists:form_fields,id'],'fields.*.is_enabled'=>['sometimes','boolean'],'fields.*.is_required'=>['sometimes','boolean'],'fields.*.label'=>['nullable','string','max:180'],'fields.*.config'=>['nullable','array'],'fields.*.sort_order'=>['sometimes','integer','min:0','max:65535']]; } }
