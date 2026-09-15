<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 250);
            $table->string('whatsapp_number', 25)->unique();
            $table->string('email', 180)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('form_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('token', 96)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_form_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_invitations');
        Schema::dropIfExists('customers');
    }
};
