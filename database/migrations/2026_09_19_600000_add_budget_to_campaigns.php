<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'budget')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->decimal('budget', 12, 2)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'budget')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->dropColumn('budget');
            });
        }
    }
};
