<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fieldIds = DB::table('form_fields')->where('key', 'delivery_time')->pluck('id');

        if ($fieldIds->isNotEmpty()) {
            DB::table('campaign_form_fields')
                ->whereIn('form_field_id', $fieldIds)
                ->update(['is_enabled' => true, 'is_required' => true]);
        }
    }

    public function down(): void
    {
        // Existing required/disabled choices cannot be reconstructed safely.
    }
};
