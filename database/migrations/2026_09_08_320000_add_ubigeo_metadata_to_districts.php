<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_ubigeo_id')->nullable()->after('id');
            $table->string('department_code', 2)->nullable()->after('department');
            $table->string('province_code', 4)->nullable()->after('department_code');

            $table->unique('legacy_ubigeo_id');
            $table->index(['department_code', 'province_code']);
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table): void {
            $table->dropUnique(['legacy_ubigeo_id']);
            $table->dropIndex(['department_code', 'province_code']);
            $table->dropColumn(['legacy_ubigeo_id', 'department_code', 'province_code']);
        });
    }
};
