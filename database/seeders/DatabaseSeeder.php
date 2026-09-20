<?php

namespace Database\Seeders;

use App\Models\FormField;
use App\Models\FormTemplate;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Ver campañas', 'slug' => 'campaigns.view', 'module' => 'campaigns', 'action' => 'view'],
            ['name' => 'Gestionar campañas', 'slug' => 'campaigns.manage', 'module' => 'campaigns', 'action' => 'manage'],
            ['name' => 'Ver sucursales', 'slug' => 'branches.view', 'module' => 'branches', 'action' => 'view'],
            ['name' => 'Gestionar sucursales', 'slug' => 'branches.manage', 'module' => 'branches', 'action' => 'manage'],
            ['name' => 'Ver distritos', 'slug' => 'districts.view', 'module' => 'districts', 'action' => 'view'],
            ['name' => 'Gestionar distritos', 'slug' => 'districts.manage', 'module' => 'districts', 'action' => 'manage'],
            ['name' => 'Ver plantillas de distritos', 'slug' => 'district_lists.view', 'module' => 'district_lists', 'action' => 'view'],
            ['name' => 'Gestionar plantillas de distritos', 'slug' => 'district_lists.manage', 'module' => 'district_lists', 'action' => 'manage'],
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'module' => 'users', 'action' => 'view'],
            ['name' => 'Gestionar usuarios', 'slug' => 'users.manage', 'module' => 'users', 'action' => 'manage'],
            ['name' => 'Ver usuarios de campaña', 'slug' => 'campaigns.users.view', 'module' => 'campaigns.users', 'action' => 'view'],
            ['name' => 'Gestionar usuarios de campaña', 'slug' => 'campaigns.users.manage', 'module' => 'campaigns.users', 'action' => 'manage'],
            ['name' => 'Ver pedidos', 'slug' => 'orders.view', 'module' => 'orders', 'action' => 'view'],
            ['name' => 'Gestionar pedidos', 'slug' => 'orders.manage', 'module' => 'orders', 'action' => 'manage'],
            ['name' => 'Gestionar rutas', 'slug' => 'routes.manage', 'module' => 'routes', 'action' => 'manage'],
            ['name' => 'Importar pedidos', 'slug' => 'imports.create', 'module' => 'imports', 'action' => 'create'],
            ['name' => 'Ver productos', 'slug' => 'products.view', 'module' => 'products', 'action' => 'view'],
            ['name' => 'Gestionar productos', 'slug' => 'products.manage', 'module' => 'products', 'action' => 'manage'],
            ['name' => 'Ver formularios', 'slug' => 'forms.view', 'module' => 'forms', 'action' => 'view'],
            ['name' => 'Gestionar formularios', 'slug' => 'forms.manage', 'module' => 'forms', 'action' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $roles = [
            'super_admin' => ['name' => 'Super administrador', 'description' => 'Acceso completo al sistema.'],
            'campaign_manager' => ['name' => 'Administrador operativo', 'description' => 'Administra integralmente las campañas, formularios, invitaciones y pedidos que tenga asignados.'],
            'dispatcher' => ['name' => 'Despachador', 'description' => 'Organiza pedidos, rutas y motorizados.'],
            'courier' => ['name' => 'Motorizado', 'description' => 'Consulta y ejecuta sus rutas asignadas.'],
            'viewer' => ['name' => 'Consulta', 'description' => 'Acceso de solo lectura.'],
        ];

        foreach ($roles as $slug => $roleData) {
            $role = Role::updateOrCreate(['slug' => $slug], $roleData + ['slug' => $slug]);

            $role->permissions()->sync(
                $slug === 'super_admin'
                    ? Permission::query()->pluck('id')
                    : Permission::query()->whereIn('slug', match ($slug) {
                        'campaign_manager' => ['campaigns.view', 'campaigns.manage', 'branches.view', 'districts.view', 'orders.view', 'orders.manage', 'imports.create', 'products.view', 'products.manage', 'forms.view', 'forms.manage', 'district_lists.view', 'district_lists.manage', 'users.view', 'users.manage', 'routes.manage', 'campaigns.users.view', 'campaigns.users.manage'],
                        'dispatcher' => ['campaigns.view', 'branches.view', 'districts.view', 'orders.view', 'orders.manage', 'routes.manage'],
                        'courier' => ['campaigns.view', 'branches.view', 'districts.view', 'orders.view'],
                        default => ['campaigns.view', 'branches.view', 'districts.view', 'district_lists.view', 'orders.view', 'products.view', 'forms.view'],
                    })->pluck('id')
            );
        }

        $template = FormTemplate::updateOrCreate(['code' => 'campaign-order-v1'], [
            'name' => 'Formulario de pedidos de campaña',
            'description' => 'Plantilla base para pedidos con entrega.',
            'is_active' => true,
        ]);
        $fields = [
            ['key' => 'product', 'label' => 'Producto', 'type' => 'product', 'is_system' => true, 'sort_order' => 10],
            ['key' => 'sender_name', 'label' => 'Nombre del remitente', 'type' => 'text', 'is_system' => true, 'sort_order' => 20],
            ['key' => 'sender_phone', 'label' => 'Teléfono del remitente', 'type' => 'phone', 'is_system' => true, 'sort_order' => 30],
            ['key' => 'recipient_name', 'label' => 'Nombre del destinatario', 'type' => 'text', 'is_system' => true, 'sort_order' => 40],
            ['key' => 'recipient_phone', 'label' => 'Teléfono del destinatario', 'type' => 'phone', 'is_system' => true, 'sort_order' => 50],
            ['key' => 'district', 'label' => 'Distrito de entrega', 'type' => 'district', 'is_system' => true, 'sort_order' => 60],
            ['key' => 'location', 'label' => 'Ubicación de entrega', 'type' => 'map', 'is_system' => true, 'sort_order' => 70],
            ['key' => 'address', 'label' => 'Dirección referencial', 'type' => 'textarea', 'is_system' => true, 'sort_order' => 80],
            ['key' => 'delivery_date', 'label' => 'Fecha de entrega', 'type' => 'date', 'is_system' => true, 'sort_order' => 90],
            ['key' => 'delivery_time', 'label' => 'Horario de entrega', 'type' => 'time', 'is_system' => true, 'sort_order' => 100],
            ['key' => 'dedication', 'label' => 'Dedicatoria', 'type' => 'textarea', 'is_system' => false, 'sort_order' => 110],
        ];
        foreach ($fields as $field) {
            FormField::updateOrCreate(['template_id' => $template->id, 'key' => $field['key']], $field);
        }
        FormField::where('template_id', $template->id)->whereIn('key', ['product', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'district', 'location'])->update(['field_group' => 'required_base', 'is_system' => true]);
        FormField::where('template_id', $template->id)->whereIn('key', ['address', 'delivery_date', 'delivery_time', 'dedication', 'adicional'])->update(['field_group' => 'optional_base', 'is_system' => true]);
        FormField::where('template_id', $template->id)->where('key', 'dedication')->update(['label' => 'Dedicatoria']);
        FormField::updateOrCreate(['template_id' => $template->id, 'key' => 'adicional'], ['label' => 'Adicional', 'type' => 'select', 'field_group' => 'optional_base', 'is_system' => true, 'is_active' => true, 'sort_order' => 115]);
        FormField::updateOrCreate(['template_id' => $template->id, 'key' => 'photo'], ['label' => 'Foto de referencia', 'description' => 'Imagen opcional relacionada con la entrega.', 'type' => 'file', 'field_group' => 'optional_base', 'is_system' => true, 'is_active' => true, 'sort_order' => 105]);
        $this->call(DeliveryReferenceFieldSeeder::class);
    }
}
