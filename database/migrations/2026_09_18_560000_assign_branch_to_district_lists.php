<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('district_lists', 'branch_id')) {
            Schema::table('district_lists', function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->restrictOnDelete();
            });
        }

        $unassignedListIds = DB::table('district_lists')->whereNull('branch_id')->pluck('id');
        if ($unassignedListIds->isEmpty()) {
            return;
        }

        $inferredBranches = DB::table('campaign_district_lists')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_district_lists.campaign_id')
            ->whereIn('campaign_district_lists.district_list_id', $unassignedListIds)
            ->whereNotNull('campaigns.branch_id')
            ->select('campaign_district_lists.district_list_id', DB::raw('MIN(campaigns.branch_id) AS branch_id'), DB::raw('COUNT(DISTINCT campaigns.branch_id) AS branch_count'))
            ->groupBy('campaign_district_lists.district_list_id')
            ->having('branch_count', 1)
            ->get();

        foreach ($inferredBranches as $assignment) {
            DB::table('district_lists')->where('id', $assignment->district_list_id)->update(['branch_id' => $assignment->branch_id]);
        }

        $remainingListIds = DB::table('district_lists')->whereNull('branch_id')->pluck('id');
        if ($remainingListIds->isNotEmpty()) {
            $branchIds = DB::table('branches')->where('is_active', true)->whereNull('deleted_at')->orderBy('id')->pluck('id');
            if ($branchIds->count() === 1) {
                DB::table('district_lists')->whereIn('id', $remainingListIds)->update(['branch_id' => $branchIds->first()]);
            } else {
                throw new RuntimeException('No se puede asignar la sucursal de estos listados sin una inferencia unica: '.$remainingListIds->implode(', '));
            }
        }

        if (DB::table('district_lists')->whereNull('branch_id')->exists()) {
            throw new RuntimeException('No se pudo asignar branch_id a todos los listados de distritos.');
        }

        DB::statement('ALTER TABLE district_lists MODIFY branch_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('district_lists', 'branch_id')) {
            Schema::table('district_lists', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
