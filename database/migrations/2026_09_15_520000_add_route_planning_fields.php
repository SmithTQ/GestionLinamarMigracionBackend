<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('delivery_routes', function (Blueprint $table): void {
            $table->string('request_key', 100)->nullable()->unique()->after('code');
            $table->text('navigation_url')->nullable()->after('estimated_minutes');
        });

        Schema::table('route_order', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->nullable()->after('order_id');
            $table->index(['route_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('route_order', function (Blueprint $table): void {
            $table->dropIndex(['route_id', 'is_active', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('delivery_routes', function (Blueprint $table): void {
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'navigation_url']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
