<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FormSubmission extends Model
{
    protected $fillable=['campaign_form_id','order_id','submission_key','request_hash','payload','status','ip_hash'];
    protected function casts(): array { return ['payload'=>'array']; }
    public function form(){return $this->belongsTo(CampaignForm::class,'campaign_form_id');}
    public function order(){return $this->belongsTo(Order::class);}
}
