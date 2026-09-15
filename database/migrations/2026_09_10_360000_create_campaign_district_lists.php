<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_district_lists', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_list_id')->constrained('district_lists')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['campaign_id', 'district_list_id']);
            $table->index(['district_list_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_district_lists');
    }
};
