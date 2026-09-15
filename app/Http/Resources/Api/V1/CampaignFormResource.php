<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Concerns\FormatsResourceDates;
use App\Models\CampaignForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CampaignForm */
class CampaignFormResource extends JsonResource
{
    use FormatsResourceDates;

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'campaign_id' => $this->campaign_id, 'branch_id' => $this->branch_id, 'template_id' => $this->template_id, 'public_key' => $this->when($request->user() !== null, $this->public_key), 'title' => $this->title, 'description' => $this->description, 'status' => $this->status, 'published_at' => $this->formatResourceDate($this->published_at, 'd/m/Y H:i'), 'published_at_iso' => $this->formatResourceDateIso($this->published_at), 'closed_at' => $this->formatResourceDate($this->closed_at, 'd/m/Y H:i'), 'closed_at_iso' => $this->formatResourceDateIso($this->closed_at), 'campaign' => new CampaignResource($this->whenLoaded('campaign')), 'branch' => new BranchResource($this->whenLoaded('branch')), 'template' => new FormTemplateResource($this->whenLoaded('template')), 'fields' => FormFieldResource::collection($this->whenLoaded('fields'))];
    }
}
