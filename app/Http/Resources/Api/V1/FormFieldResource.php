<?php
namespace App\Http\Resources\Api\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class FormFieldResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'key'=>$this->key,'label'=>$this->label,'type'=>$this->type,'is_system'=>$this->is_system,'validation_rules'=>$this->validation_rules,'sort_order'=>$this->sort_order,'form_config'=>$this->whenPivotLoaded('campaign_form_fields', fn()=>['is_enabled'=>$this->pivot->is_enabled,'is_required'=>$this->pivot->is_required,'label'=>$this->pivot->label,'config'=>$this->pivot->config,'sort_order'=>$this->pivot->sort_order])]; } }
