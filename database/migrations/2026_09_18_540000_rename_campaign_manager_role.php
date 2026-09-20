<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'campaign_manager')
            ->update([
                'name' => 'Administrador operativo',
                'description' => 'Administra integralmente las campañas, formularios, invitaciones y pedidos que tenga asignados.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'campaign_manager')
            ->update([
                'name' => 'Gestor de campañas',
                'description' => 'Administra campañas y pedidos asignados.',
                'updated_at' => now(),
            ]);
    }
};
