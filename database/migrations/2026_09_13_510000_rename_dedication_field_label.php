<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_fields')->where('key', 'dedication')->update(['label' => 'Dedicatoria']);
    }

    public function down(): void
    {
        DB::table('form_fields')->where('key', 'dedication')->update(['label' => 'Dedicatoria u observaciones']);
    }
};
