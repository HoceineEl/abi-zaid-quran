<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppMessageHistory extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_message_histories';

    protected $fillable = [
        'session_id',
        'sender_user_id',
        'recipient_phone',
        'recipient_name',
        'message_type',
        'message_content',
        'media_data',
        'status',
        'whatsapp_message_id',
        'sent_at',
        'failed_at',
        'retry_count',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'status' => WhatsAppMessageStatus::class,
        'media_data' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function markAsSent(?string $whatsappMessageId = null): void
    {
        $this->update([
            'status' => WhatsAppMessageStatus::SENT,
            'whatsapp_message_id' => $whatsappMessageId,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => WhatsAppMessageStatus::FAILED,
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public function isRetryable(): bool
    {
        return $this->status === WhatsAppMessageStatus::FAILED && $this->retry_count < 3;
    }

    public function getFormattedMessageContentAttribute(): string
    {
        if ($this->message_type === 'text') {
            return $this->message_content;
        }

        if ($this->message_type === 'image') {
            return $this->message_content ?: 'Image message';
        }

        if ($this->message_type === 'document') {
            $fileName = $this->media_data['original_name'] ?? 'Document';

            return "Document: {$fileName}";
        }

        return $this->message_content;
    }
}
