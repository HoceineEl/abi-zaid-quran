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
            $table->timestamp('questionnaire_sent_at')->nullable()->after('student_reaction_date');
            $table->boolean('has_been_converted_to_mandatory_group')->default(false)->after('questionnaire_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->dropColumn(['questionnaire_sent_at', 'has_been_converted_to_mandatory_group']);
        });
    }
};
