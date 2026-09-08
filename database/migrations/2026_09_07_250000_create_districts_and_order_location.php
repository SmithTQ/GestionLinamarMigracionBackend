<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('province', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('macroregion', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_district', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->primary(['campaign_id', 'district_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('district_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 8, 2)->nullable();
            $table->index(['district_id', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['district_id']);
            $table->dropColumn(['district_id', 'latitude', 'longitude', 'location_accuracy']);
        });
        Schema::dropIfExists('campaign_district');
        Schema::dropIfExists('districts');
    }
};
