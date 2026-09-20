<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'module' => 'users', 'action' => 'view'],
            ['name' => 'Gestionar usuarios', 'slug' => 'users.manage', 'module' => 'users', 'action' => 'manage'],
            ['name' => 'Gestionar rutas', 'slug' => 'routes.manage', 'module' => 'routes', 'action' => 'manage'],
            ['name' => 'Ver usuarios de campaña', 'slug' => 'campaigns.users.view', 'module' => 'campaigns.users', 'action' => 'view'],
            ['name' => 'Gestionar usuarios de campaña', 'slug' => 'campaigns.users.manage', 'module' => 'campaigns.users', 'action' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + ['is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $roleId = DB::table('roles')->where('slug', 'campaign_manager')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', collect($permissions)->pluck('slug'))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'campaign_manager')->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['users.view', 'users.manage', 'routes.manage', 'campaigns.users.view', 'campaigns.users.manage'])
            ->pluck('id');

        if ($roleId) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }
    }
};
