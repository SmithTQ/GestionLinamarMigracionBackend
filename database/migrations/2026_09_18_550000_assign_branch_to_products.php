<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->restrictOnDelete();
            });
        }

        $unassignedProductIds = DB::table('products')->whereNull('branch_id')->pluck('id');
        if ($unassignedProductIds->isEmpty()) {
            return;
        }

        $inferredBranches = DB::table('campaign_product')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_product.campaign_id')
            ->whereIn('campaign_product.product_id', $unassignedProductIds)
            ->whereNotNull('campaigns.branch_id')
            ->select('campaign_product.product_id', DB::raw('MIN(campaigns.branch_id) AS branch_id'), DB::raw('COUNT(DISTINCT campaigns.branch_id) AS branch_count'))
            ->groupBy('campaign_product.product_id')
            ->having('branch_count', 1)
            ->get();

        foreach ($inferredBranches as $assignment) {
            DB::table('products')->where('id', $assignment->product_id)->update(['branch_id' => $assignment->branch_id]);
        }

        $remainingProductIds = DB::table('products')->whereNull('branch_id')->pluck('id');
        if ($remainingProductIds->isNotEmpty()) {
            $branchIds = DB::table('branches')->whereNull('deleted_at')->orderBy('id')->pluck('id');
            if ($branchIds->count() === 1) {
                DB::table('products')->whereIn('id', $remainingProductIds)->update(['branch_id' => $branchIds->first()]);
            } else {
                throw new RuntimeException('No se puede asignar la sucursal de estos productos sin una inferencia unica: '.$remainingProductIds->implode(', '));
            }
        }

        if (DB::table('products')->whereNull('branch_id')->exists()) {
            throw new RuntimeException('No se puede completar la migracion: existen productos sin sucursal.');
        }

        DB::statement('ALTER TABLE products MODIFY branch_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
