<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('external_source', 60)->nullable();
            $table->string('external_key', 150)->nullable();
            $table->unsignedInteger('order_number')->nullable();
            $table->string('product_name', 150);
            $table->string('sender_name', 250);
            $table->string('sender_phone', 30);
            $table->string('recipient_name', 250);
            $table->string('recipient_phone', 30);
            $table->string('district', 120);
            $table->string('address', 255);
            $table->text('dedication')->nullable();
            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_id', 'external_source', 'external_key'], 'orders_source_key_unique');
            $table->index(['campaign_id', 'branch_id', 'status']);
            $table->index(['delivery_date', 'district']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
