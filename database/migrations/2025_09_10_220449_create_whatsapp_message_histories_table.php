<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('whatsapp_sessions');
            $table->foreignId('sender_user_id')->nullable()->constrained('users');
            $table->string('recipient_phone');
            $table->string('recipient_name')->nullable();
            $table->enum('message_type', ['text', 'image', 'document', 'audio'])->default('text');
            $table->text('message_content');
            $table->json('media_data')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'cancelled'])->default('queued');
            $table->string('whatsapp_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_histories');
    }
};
