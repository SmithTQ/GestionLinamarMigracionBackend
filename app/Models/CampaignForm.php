<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CampaignForm extends Model
{
    use SoftDeletes;
    protected $fillable = ['campaign_id', 'branch_id', 'template_id', 'public_key', 'title', 'description', 'status', 'published_at', 'closed_at'];
    protected function casts(): array { return ['published_at' => 'datetime', 'closed_at' => 'datetime']; }
    public function campaign() { return $this->belongsTo(Campaign::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function template() { return $this->belongsTo(FormTemplate::class); }
    public function fields() { return $this->belongsToMany(FormField::class, 'campaign_form_fields')->withPivot(['is_enabled', 'is_required', 'label', 'config', 'sort_order']); }
}
