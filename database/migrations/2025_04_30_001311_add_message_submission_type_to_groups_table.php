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
        Schema::table('groups', function (Blueprint $table) {
            $table->enum('message_submission_type', ['media', 'text', 'both'])->default('media')->after('is_onsite');
            $table->json('ignored_names_phones')->nullable()->after('message_submission_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('message_submission_type');
            $table->dropColumn('ignored_names_phones');
        });
    }
};
