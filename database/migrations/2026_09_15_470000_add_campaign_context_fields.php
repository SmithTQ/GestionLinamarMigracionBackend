<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_user', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index()->after('user_id');
            $table->timestamps();
        });

        Schema::table('form_invitations', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->index(['campaign_form_id', 'status']);
        });

        $now = now();
        $permissions = [
            ['name' => 'Ver usuarios de campana', 'slug' => 'campaigns.users.view', 'module' => 'campaigns.users', 'action' => 'view'],
            ['name' => 'Gestionar usuarios de campana', 'slug' => 'campaigns.users.manage', 'module' => 'campaigns.users', 'action' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['super_admin', 'campaign_manager'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['campaigns.users.view', 'campaigns.users.manage'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('form_invitations', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
            $table->dropIndex(['campaign_form_id', 'status']);
        });
        Schema::table('campaign_user', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'created_at', 'updated_at']);
        });
    }
};
