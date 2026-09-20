<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table): void {
            $table->dropUnique(['legacy_ubigeo_id']);
            $table->dropColumn('legacy_ubigeo_id');
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_ubigeo_id')->nullable()->after('id');
            $table->unique('legacy_ubigeo_id');
        });
    }
};
