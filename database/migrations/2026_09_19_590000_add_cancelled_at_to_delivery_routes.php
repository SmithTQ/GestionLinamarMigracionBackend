<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_routes', 'cancelled_at')) {
            Schema::table('delivery_routes', function (Blueprint $table): void {
                $table->timestamp('cancelled_at')->nullable()->after('dispatched_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_routes', 'cancelled_at')) {
            Schema::table('delivery_routes', function (Blueprint $table): void {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
