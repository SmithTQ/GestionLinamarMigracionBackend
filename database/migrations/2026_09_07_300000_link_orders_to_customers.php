<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('orders', function(Blueprint $table): void { $table->foreignId('customer_id')->nullable()->after('branch_id')->constrained()->nullOnDelete(); $table->index(['customer_id','campaign_id']); }); } public function down(): void { Schema::table('orders', function(Blueprint $table): void { $table->dropForeign(['customer_id']); $table->dropColumn('customer_id'); }); } };
