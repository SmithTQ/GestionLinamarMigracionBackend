<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Ver distritos', 'slug' => 'districts.view', 'module' => 'districts', 'action' => 'view'],
            ['name' => 'Gestionar distritos', 'slug' => 'districts.manage', 'module' => 'districts', 'action' => 'manage'],
            ['name' => 'Ver plantillas de distritos', 'slug' => 'district_lists.view', 'module' => 'district_lists', 'action' => 'view'],
            ['name' => 'Gestionar plantillas de distritos', 'slug' => 'district_lists.manage', 'module' => 'district_lists', 'action' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $rolePermissions = [
            'super_admin' => array_keys($permissionIds->all()),
            'campaign_manager' => ['campaigns.view', 'campaigns.manage', 'branches.view', 'districts.view', 'orders.view', 'orders.manage', 'imports.create', 'products.view', 'products.manage', 'forms.view', 'forms.manage', 'district_lists.view', 'district_lists.manage'],
            'dispatcher' => ['campaigns.view', 'branches.view', 'districts.view', 'orders.view', 'orders.manage', 'routes.manage'],
            'courier' => ['campaigns.view', 'branches.view', 'districts.view', 'orders.view'],
            'viewer' => ['campaigns.view', 'branches.view', 'districts.view', 'district_lists.view', 'orders.view', 'products.view', 'forms.view'],
        ];

        foreach ($rolePermissions as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            $rows = collect($slugs)
                ->filter(fn (string $slug): bool => isset($permissionIds[$slug]))
                ->map(fn (string $slug): array => ['role_id' => $roleId, 'permission_id' => $permissionIds[$slug]])
                ->values()
                ->all();

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        $slugs = ['districts.view', 'districts.manage'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
