<?php
namespace App\Http\Resources\Api\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class CampaignFormResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'campaign_id'=>$this->campaign_id,'branch_id'=>$this->branch_id,'template_id'=>$this->template_id,'public_key'=>$this->when($request->user() !== null, $this->public_key),'title'=>$this->title,'description'=>$this->description,'status'=>$this->status,'published_at'=>$this->published_at?->toISOString(),'closed_at'=>$this->closed_at?->toISOString(),'campaign'=>new CampaignResource($this->whenLoaded('campaign')),'branch'=>new BranchResource($this->whenLoaded('branch')),'template'=>new FormTemplateResource($this->whenLoaded('template')),'fields'=>FormFieldResource::collection($this->whenLoaded('fields'))]; } }
