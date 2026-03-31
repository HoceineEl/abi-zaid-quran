<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            $table->dropForeign(['sender_user_id']);
            $table->foreign('sender_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_histories', function (Blueprint $table) {
            $table->dropForeign(['sender_user_id']);
            $table->foreign('sender_user_id')
                ->references('id')
                ->on('users');
        });
    }
};
