<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrate old message_response statuses to new ones:
     * - Old 'contacted' status is removed (no longer needed)
     * - Old 'yes' stays as 'yes' (positive response)
     * - Old 'no' stays as 'no' (negative response)
     * - Old 'not_contacted' stays as 'not_contacted' (never contacted)
     * - New 'reminder_message' and 'warning_message' will be set by the new actions
     */
    public function up(): void
    {
        // No database schema changes needed since we're just updating values
        // The message_response column already stores varchar values

        // Migrate any records with old 'contacted' status to 'no' (negative response)
        // This assumes the old 'contacted' status meant someone was contacted but didn't respond positively
        DB::table('student_disconnections')
            ->where('message_response', 'contacted')
            ->update(['message_response' => 'no']);

        // Log the migration
        \Illuminate\Support\Facades\Log::info('Migrated student_disconnection message_response statuses', [
            'old_status' => 'contacted',
            'new_status' => 'no',
            'reason' => 'Old contacted status converted to negative response (no)'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the migration - convert 'no' back to 'contacted' only if they have a contact_date
        // This is a best-effort rollback
        DB::table('student_disconnections')
            ->where('message_response', 'no')
            ->whereNotNull('contact_date')
            ->update(['message_response' => 'contacted']);

        \Illuminate\Support\Facades\Log::info('Rolled back student_disconnection message_response statuses', [
            'old_status' => 'no',
            'new_status' => 'contacted',
        ]);
    }
};
