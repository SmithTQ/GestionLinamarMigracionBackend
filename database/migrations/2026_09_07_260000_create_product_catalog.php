<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['category_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subcategory_id')->nullable()->constrained('product_subcategories')->nullOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('unit', 40)->default('unidad');
            $table->decimal('base_price', 12, 2)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subcategory_id', 'is_active']);
        });

        Schema::create('campaign_product', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->timestamps();
            $table->primary(['campaign_id', 'product_id']);
            $table->index(['campaign_id', 'is_available']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->decimal('product_price', 12, 2)->nullable()->after('product_name');
            $table->index(['product_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'product_price']);
        });
        Schema::dropIfExists('campaign_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_subcategories');
        Schema::dropIfExists('product_categories');
    }
};
