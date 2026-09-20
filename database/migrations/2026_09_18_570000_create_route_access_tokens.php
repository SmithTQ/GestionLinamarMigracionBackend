<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $created = false;
        if (! Schema::hasTable('route_access_tokens')) {
            Schema::create('route_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('delivery_route_id')->constrained('delivery_routes')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index(['delivery_route_id', 'revoked_at', 'expires_at'], 'route_access_route_state_idx');
            });
            $created = true;
        }

        $indexExists = $created || DB::connection()->getDriverName() === 'sqlite' || DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'route_access_tokens')
            ->where('index_name', 'route_access_route_state_idx')
            ->exists();

        if (! $indexExists) {
            Schema::table('route_access_tokens', function (Blueprint $table): void {
                $table->index(['delivery_route_id', 'revoked_at', 'expires_at'], 'route_access_route_state_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('route_access_tokens');
    }
};
