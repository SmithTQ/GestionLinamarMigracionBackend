<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FormInvitation extends Model { protected $fillable=['campaign_form_id','customer_id','created_by_user_id','revoked_by_user_id','token','status','expires_at','used_at','revoked_at']; protected function casts():array{return ['expires_at'=>'datetime','used_at'=>'datetime','revoked_at'=>'datetime'];} public function form(){return $this->belongsTo(CampaignForm::class,'campaign_form_id');} public function customer(){return $this->belongsTo(Customer::class);} public function createdBy(){return $this->belongsTo(User::class,'created_by_user_id');} }
