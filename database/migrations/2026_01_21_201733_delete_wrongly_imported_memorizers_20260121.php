<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run on 2026-01-21 to fix wrongly imported memorizers after 22:00
        if (now()->format('Y-m-d') === '2026-01-21') {
            DB::table('memorizers')
                ->where('created_at', '>=', '2026-01-21 22:00:00')
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot restore deleted records
    }
};
