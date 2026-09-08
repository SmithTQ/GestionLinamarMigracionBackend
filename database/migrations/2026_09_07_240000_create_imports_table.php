<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 60)->default('google_sheets');
            $table->string('spreadsheet_id', 255);
            $table->string('range', 255);
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('duplicated_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
