<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->string('student_reaction')->nullable()->after('message_response')->comment('Student reaction status: reacted_to_reminder, reacted_to_warning, positive_response, negative_response, no_response');
            $table->date('student_reaction_date')->nullable()->after('student_reaction')->comment('Date when student reacted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->dropColumn(['student_reaction', 'student_reaction_date']);
        });
    }
};
