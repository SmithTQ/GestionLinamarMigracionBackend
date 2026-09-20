<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_delivery_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_route_id')->constrained('delivery_routes')->restrictOnDelete();
            $table->foreignId('delivered_by_courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('delivered_at');
            $table->timestamps();
            $table->unique(['delivery_route_id', 'order_id'], 'delivery_evidence_route_order_unique');
            $table->index(['order_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_delivery_evidences');
    }
};
