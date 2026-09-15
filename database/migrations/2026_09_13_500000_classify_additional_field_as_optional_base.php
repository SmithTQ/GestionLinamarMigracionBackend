<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_fields')->where('key', 'adicional')->update([
            'field_group' => 'optional_base',
            'is_system' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('form_fields')->where('key', 'adicional')->update([
            'field_group' => 'custom',
            'is_system' => false,
        ]);
    }
};
