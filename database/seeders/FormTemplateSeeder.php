<?php

namespace Database\Seeders;

use App\Models\FormField;
use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = FormTemplate::updateOrCreate(['code' => 'campaign-order-v1'], [
            'name' => 'Formulario de pedidos de campaña',
            'description' => 'Plantilla base para pedidos con entrega.',
            'is_active' => true,
        ]);

        $fields = [
            ['key' => 'product', 'label' => 'Producto', 'type' => 'product', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 10],
            ['key' => 'sender_name', 'label' => 'Nombre del remitente', 'type' => 'text', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 20],
            ['key' => 'sender_phone', 'label' => 'Teléfono del remitente', 'type' => 'phone', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 30],
            ['key' => 'recipient_name', 'label' => 'Nombre del destinatario', 'type' => 'text', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 40],
            ['key' => 'recipient_phone', 'label' => 'Teléfono del destinatario', 'type' => 'phone', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 50],
            ['key' => 'district', 'label' => 'Distrito de entrega', 'type' => 'district', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 60],
            ['key' => 'location', 'label' => 'Ubicación de entrega', 'type' => 'map', 'field_group' => 'required_base', 'is_system' => true, 'sort_order' => 70],
            ['key' => 'address', 'label' => 'Dirección referencial', 'type' => 'textarea', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 80],
            ['key' => 'delivery_date', 'label' => 'Fecha de entrega', 'type' => 'date', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 90],
            ['key' => 'delivery_time', 'label' => 'Horario de entrega', 'type' => 'time', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 100],
            ['key' => 'dedication', 'label' => 'Dedicatoria', 'type' => 'textarea', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 110],
            ['key' => 'adicional', 'label' => 'Adicional', 'type' => 'select', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 115],
            ['key' => 'photo', 'label' => 'Foto de referencia', 'description' => 'Imagen opcional relacionada con la entrega.', 'type' => 'file', 'field_group' => 'optional_base', 'is_system' => true, 'sort_order' => 105],
        ];

        foreach ($fields as $field) {
            FormField::updateOrCreate(
                ['template_id' => $template->id, 'key' => $field['key']],
                $field
            );
        }
        $this->call(DeliveryReferenceFieldSeeder::class);
    }
}
