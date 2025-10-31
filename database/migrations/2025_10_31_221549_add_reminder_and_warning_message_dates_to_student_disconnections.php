<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add separate date columns for tracking when reminder and warning messages are sent
     */
    public function up(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->date('reminder_message_date')
                ->nullable()
                ->after('contact_date')
                ->comment('Date when reminder message was sent');

            $table->date('warning_message_date')
                ->nullable()
                ->after('reminder_message_date')
                ->comment('Date when warning message was sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->dropColumn('reminder_message_date');
            $table->dropColumn('warning_message_date');
        });
    }
};
