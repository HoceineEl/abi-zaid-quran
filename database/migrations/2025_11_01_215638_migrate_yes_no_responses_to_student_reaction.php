<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrate existing 'yes' and 'no' values from message_response to student_reaction
     */
    public function up(): void
    {
        // Migrate 'yes' to 'reacted_to_reminder' (as requested by user)
        DB::table('student_disconnections')
            ->where('message_response', 'yes')
            ->update([
                'student_reaction' => 'reacted_to_reminder',
                'student_reaction_date' => DB::raw('contact_date'),
                'message_response' => 'reminder_message',
            ]);

        // Migrate 'no' to 'no_response'
        DB::table('student_disconnections')
            ->where('message_response', 'no')
            ->update([
                'student_reaction' => 'no_response',
                'student_reaction_date' => DB::raw('contact_date'),
                'message_response' => 'not_contacted',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore 'yes' responses
        DB::table('student_disconnections')
            ->where('student_reaction', 'reacted_to_reminder')
            ->whereNotNull('student_reaction_date')
            ->update([
                'message_response' => 'yes',
                'student_reaction' => null,
                'student_reaction_date' => null,
            ]);

        // Restore 'no' responses
        DB::table('student_disconnections')
            ->where('student_reaction', 'no_response')
            ->whereNotNull('student_reaction_date')
            ->update([
                'message_response' => 'no',
                'student_reaction' => null,
                'student_reaction_date' => null,
            ]);
    }
};
