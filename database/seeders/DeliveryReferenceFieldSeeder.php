<?php

namespace Database\Seeders;

use App\Models\FormField;
use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class DeliveryReferenceFieldSeeder extends Seeder
{
    public function run(): void
    {
        $template = FormTemplate::where('code', 'campaign-order-v1')->first();
        if ($template === null) {
            return;
        }

        FormField::updateOrCreate(
            ['template_id' => $template->id, 'key' => 'delivery_reference'],
            [
                'label' => 'Referencia de entrega',
                'description' => 'Referencia visual para ubicar el punto de entrega. Ejemplo: Casa azul de tres pisos, frente al parque.',
                'type' => 'textarea',
                'field_group' => 'required_base',
                'is_system' => true,
                'is_active' => true,
                'validation_rules' => ['max_length' => 1000],
                'sort_order' => 75,
            ]
        );
    }
}
