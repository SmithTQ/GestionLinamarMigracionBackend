<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $ambiguous = DB::table('campaigns')
            ->leftJoin('campaign_branch', 'campaign_branch.campaign_id', '=', 'campaigns.id')
            ->select('campaigns.id', 'campaigns.code', DB::raw('COUNT(campaign_branch.branch_id) AS branch_count'))
            ->groupBy('campaigns.id', 'campaigns.code')
            ->havingRaw('COUNT(campaign_branch.branch_id) <> 1')
            ->get();

        if ($ambiguous->isNotEmpty()) {
            $details = $ambiguous->map(fn ($campaign): string => $campaign->id.' ('.$campaign->code.'): '.$campaign->branch_count.' sucursales')->implode('; ');
            throw new RuntimeException('No se puede migrar campaigns.branch_id. Resolver primero estas campanas: '.$details);
        }

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('name')->constrained('branches')->restrictOnDelete();
        });

        DB::table('campaign_branch')
            ->select('campaign_id', 'branch_id')
            ->orderBy('campaign_id')
            ->each(function (object $assignment): void {
                DB::table('campaigns')
                    ->where('id', $assignment->campaign_id)
                    ->update(['branch_id' => $assignment->branch_id]);
            });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            });
        }

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->index('branch_id');
        });

        Schema::dropIfExists('campaign_branch');
    }

    public function down(): void
    {
        Schema::create('campaign_branch', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['campaign_id', 'branch_id']);
        });

        DB::table('campaigns')
            ->whereNotNull('branch_id')
            ->select('id', 'branch_id')
            ->orderBy('id')
            ->each(function (object $campaign): void {
                DB::table('campaign_branch')->insert([
                    'campaign_id' => $campaign->id,
                    'branch_id' => $campaign->branch_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
