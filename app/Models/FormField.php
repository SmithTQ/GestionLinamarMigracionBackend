<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FormField extends Model
{
    protected $fillable = ['template_id', 'key', 'label', 'type', 'is_system', 'validation_rules', 'sort_order'];
    protected function casts(): array { return ['is_system' => 'boolean', 'validation_rules' => 'array']; }
    public function template() { return $this->belongsTo(FormTemplate::class, 'template_id'); }
}
