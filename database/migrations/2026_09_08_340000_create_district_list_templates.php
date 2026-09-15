<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('district_list_items', function (Blueprint $table): void {
            $table->foreignId('district_list_id')->constrained('district_lists')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['district_list_id', 'district_id']);
            $table->index(['district_list_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_list_items');
        Schema::dropIfExists('district_lists');
    }
};
