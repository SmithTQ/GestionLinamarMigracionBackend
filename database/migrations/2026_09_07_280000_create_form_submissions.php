<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('form_submissions', function (Blueprint $table): void { $table->id(); $table->foreignId('campaign_form_id')->constrained()->cascadeOnDelete(); $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $table->string('submission_key',100); $table->string('request_hash',64)->index(); $table->json('payload'); $table->string('status',30)->default('accepted'); $table->string('ip_hash',64)->nullable(); $table->timestamps(); $table->unique(['campaign_form_id','submission_key']); }); }
    public function down(): void { Schema::dropIfExists('form_submissions'); }
};
