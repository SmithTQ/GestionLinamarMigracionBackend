<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('description', 250)->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['phone', 'is_active'], 'couriers_phone_active_unique');
        });

        Schema::create('branch_courier', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'courier_id']);
        });

        Schema::create('delivery_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('total_distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['campaign_id', 'branch_id', 'code'], 'routes_campaign_branch_code_unique');
        });

        Schema::create('route_order', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['route_id', 'order_id']);
            $table->index(['order_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_order');
        Schema::dropIfExists('delivery_routes');
        Schema::dropIfExists('branch_courier');
        Schema::dropIfExists('couriers');
    }
};
