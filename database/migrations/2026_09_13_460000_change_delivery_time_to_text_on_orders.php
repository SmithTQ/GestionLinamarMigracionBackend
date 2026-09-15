<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Preserve existing time values in a compact, readable text format.
        DB::statement("UPDATE orders SET delivery_time = DATE_FORMAT(delivery_time, '%H:%i') WHERE delivery_time IS NOT NULL");
        DB::statement('ALTER TABLE orders MODIFY delivery_time VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Only HH:MM values can be safely converted back to a TIME column.
        DB::statement("UPDATE orders SET delivery_time = NULL WHERE delivery_time IS NOT NULL AND delivery_time NOT REGEXP '^[0-9]{2}:[0-9]{2}$'");
        DB::statement('ALTER TABLE orders MODIFY delivery_time TIME NULL');
    }
};
