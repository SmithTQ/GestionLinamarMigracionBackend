<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 180);
            $table->string('type', 40);
            $table->boolean('is_system')->default(false);
            $table->json('validation_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['template_id', 'key']);
        });
        Schema::create('campaign_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('form_templates')->restrictOnDelete();
            $table->string('public_key', 80)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['campaign_id', 'branch_id']);
        });
        Schema::create('campaign_form_fields', function (Blueprint $table): void {
            $table->foreignId('campaign_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->string('label', 180)->nullable();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['campaign_form_id', 'form_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_form_fields');
        Schema::dropIfExists('campaign_forms');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_templates');
    }
};
