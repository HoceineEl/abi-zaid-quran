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
        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['session_id']);
        });

        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            // Add the foreign key constraint with cascade delete
            $table->foreign('session_id')
                ->references('id')
                ->on('whatsapp_sessions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            // Drop the cascade foreign key constraint
            $table->dropForeign(['session_id']);
        });

        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            // Restore the original foreign key constraint
            $table->foreign('session_id')
                ->references('id')
                ->on('whatsapp_sessions');
        });
    }
};
