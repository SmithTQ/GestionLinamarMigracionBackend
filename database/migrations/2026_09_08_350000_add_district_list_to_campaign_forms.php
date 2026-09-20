<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_forms', function (Blueprint $table): void {
            $table->foreignId('district_list_id')->nullable()->after('template_id')->constrained('district_lists')->nullOnDelete();
            $table->index(['district_list_id', 'status']);
        });

        Schema::create('campaign_form_districts', function (Blueprint $table): void {
            $table->foreignId('campaign_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('province', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('macroregion', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['campaign_form_id', 'district_id']);
            $table->index(['campaign_form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_form_districts');
        Schema::table('campaign_forms', function (Blueprint $table): void {
            $table->dropIndex(['district_list_id', 'status']);
            $table->dropForeign(['district_list_id']);
            $table->dropColumn('district_list_id');
        });
    }
};
