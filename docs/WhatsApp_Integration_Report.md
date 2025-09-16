# WhatsApp Integration for Laravel Applications: Complete Implementation Guide

## Overview

This report provides a comprehensive guide for implementing WhatsApp integration in Laravel applications, including connection management, QR code authentication, message sending, and user interface components. The implementation uses a WhatsApp Web API service and provides a complete solution for business messaging.

## Architecture Overview

### Core Components

1. **Session Management** - Handle WhatsApp connection sessions
2. **Message Handling** - Send and track messages
3. **QR Code Authentication** - Manage WhatsApp Web authentication
4. **Status Tracking** - Monitor connection and message statuses
5. **User Interface** - Admin panels and user-facing components

## 1. Database Schema

### WhatsApp Sessions Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('status')->default('disconnected');
            $table->longText('qr_code')->nullable();
            $table->json('session_data')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
```

### WhatsApp Message History Table

```php
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
            $table->enum('message_type', ['text', 'image', 'document', 'audio']);
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
```

## 2. Enums for Status Management

### WhatsApp Connection Status

```php
<?php

namespace App\Enums;

enum WhatsAppConnectionStatus: string
{
    case DISCONNECTED = 'disconnected';
    case CREATING = 'creating';
    case CONNECTING = 'connecting';
    case PENDING = 'pending';
    case GENERATING_QR = 'generating_qr';
    case CONNECTED = 'connected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DISCONNECTED => 'Disconnected',
            self::CREATING => 'Creating Session',
            self::CONNECTING => 'Connecting',
            self::PENDING => 'Pending',
            self::GENERATING_QR => 'Generating QR Code',
            self::CONNECTED => 'Connected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DISCONNECTED => 'gray',
            self::CREATING, self::CONNECTING, self::GENERATING_QR => 'info',
            self::PENDING => 'warning',
            self::CONNECTED => 'success',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::CONNECTED,
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR
        ]);
    }

    public function canShowQrCode(): bool
    {
        return in_array($this, [
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR
        ]);
    }

    public static function fromApiStatus(string $apiStatus): self
    {
        return match (strtoupper($apiStatus)) {
            'CONNECTED' => self::CONNECTED,
            'GENERATING_QR' => self::GENERATING_QR,
            'CREATING' => self::CREATING,
            'CONNECTING' => self::CONNECTING,
            'PENDING' => self::PENDING,
            'DISCONNECTED' => self::DISCONNECTED,
            default => self::DISCONNECTED,
        };
    }
}
```

### WhatsApp Message Status

```php
<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::QUEUED => 'Queued',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::QUEUED => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SENT, self::CANCELLED]);
    }
}
```

## 3. Models

### WhatsApp Session Model

```php
<?php

namespace App\Models;

use App\Enums\WhatsAppConnectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class WhatsAppSession extends Model
{
    protected $casts = [
        'status' => WhatsAppConnectionStatus::class,
        'connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'session_data' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessageHistory::class, 'session_id');
    }

    public function isConnected(): bool
    {
        return $this->status === WhatsAppConnectionStatus::CONNECTED;
    }

    public function markAsConnected(?array $sessionData = null): void
    {
        $updateData = [
            'status' => WhatsAppConnectionStatus::CONNECTED,
            'connected_at' => now(),
            'last_activity_at' => now(),
        ];

        if ($sessionData !== null) {
            $updateData['session_data'] = $sessionData;

            if (isset($sessionData['token']) && !empty($sessionData['token'])) {
                Cache::put("whatsapp_token_{$this->id}", $sessionData['token'], now()->addHours(24));
            }
        }

        $this->update($updateData);
    }

    public function markAsDisconnected(): void
    {
        Cache::forget("whatsapp_token_{$this->id}");

        $this->update([
            'status' => WhatsAppConnectionStatus::DISCONNECTED,
            'connected_at' => null,
            'qr_code' => null,
        ]);
    }

    public function updateQrCode(?string $qrCode): void
    {
        $cleanedQrCode = $this->processQrCodeData($qrCode);

        $this->update([
            'qr_code' => $cleanedQrCode,
            'last_activity_at' => now(),
        ]);
    }

    public function getQrCodeData(): ?string
    {
        return $this->processQrCodeData($this->qr_code);
    }

    protected function processQrCodeData(?string $qrData): ?string
    {
        if (empty($qrData)) {
            return null;
        }

        // If it's already a proper data URL, return as is
        if (str_starts_with($qrData, 'data:image/')) {
            return $qrData;
        }

        // If it's just base64 data, convert to proper data URL
        if (base64_decode($qrData, true) !== false) {
            return 'data:image/png;base64,' . $qrData;
        }

        return null;
    }

    public function getSessionToken(): ?string
    {
        return $this->session_data['token'] ?? null;
    }

    public static function getActiveSession(): ?self
    {
        return static::query()
            ->where('status', WhatsAppConnectionStatus::CONNECTED)
            ->first();
    }
}
```

### WhatsApp Message History Model

```php
<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppMessageHistory extends Model
{
    use SoftDeletes;

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
```

## 4. WhatsApp Service

### Main Service Class

```php
<?php

namespace App\Services;

class WhatsAppService
{
    use SessionManagement;
    use MessageSending;
    use MediaHandling;

    protected string $baseUrl;
    protected ?string $masterKey;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_url', 'http://localhost:3000');
        $this->masterKey = config('whatsapp.api_token');

        if (is_null($this->masterKey)) {
            \Log::warning('WhatsApp API token is null. Check whatsapp.api_token config.');
        }
    }

    protected function getMasterKey(): string
    {
        if (is_null($this->masterKey)) {
            throw new \Exception('WhatsApp API token is not configured.');
        }

        return $this->masterKey;
    }
}
```

### Session Management Trait

```php
<?php

namespace App\Traits\WhatsApp;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait SessionManagement
{
    public function createSession(string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'X-Master-Key' => $this->getMasterKey(),
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions", [
                'sessionId' => $sessionId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Cache::put("whatsapp_token_{$sessionId}", $data['token'], now()->addHours(24));
                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getSessionStatus(string $sessionId): array
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/v1/sessions");

            if ($response->successful()) {
                $sessions = $response->json();
                $session = collect($sessions)->firstWhere('sessionId', $sessionId);

                if (!$session) {
                    throw new \Exception("Session {$sessionId} not found");
                }

                // Update cached token if it exists in the response
                if (isset($session['token']) && !empty($session['token'])) {
                    $currentToken = Cache::get("whatsapp_token_{$sessionId}");
                    $newToken = $session['token'];

                    if ($currentToken !== $newToken) {
                        Cache::put("whatsapp_token_{$sessionId}", $newToken, now()->addHours(24));
                    }
                }

                return $session;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp session status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function startSession(WhatsAppSession $session): array
    {
        try {
            $sessionId = $session->id;
            $result = $this->createSession($sessionId);

            // Wait for QR code generation
            $attempts = 0;
            $maxAttempts = 30;

            while ($attempts < $maxAttempts) {
                $status = $this->getSessionStatus($sessionId);

                if (!empty($status['qr']) || $status['status'] === 'CONNECTED') {
                    $result = $status;
                    break;
                }

                if ($status['status'] === 'GENERATING_QR') {
                    sleep(2);
                    $attempts += 2;
                    continue;
                }

                sleep(1);
                $attempts++;
            }

            $finalStatus = $this->getSessionStatus($sessionId);
            $modelStatus = WhatsAppConnectionStatus::fromApiStatus($finalStatus['status'] ?? 'pending');

            $session->update([
                'status' => $modelStatus,
                'qr_code' => $finalStatus['qr'] ?? null,
                'session_data' => $finalStatus,
                'last_activity_at' => now(),
            ]);

            return $finalStatus;
        } catch (\Exception $e) {
            Log::error('Failed to start WhatsApp session', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteSession(string $sessionId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if ($token) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ])->delete("{$this->baseUrl}/api/v1/sessions/{$sessionId}");

                if ($response->successful()) {
                    Cache::forget("whatsapp_token_{$sessionId}");
                    Log::info('WhatsApp session deleted', ['session_id' => $sessionId]);
                    return $response->json();
                }
            }

            Cache::forget("whatsapp_token_{$sessionId}");
            return ['status' => 'success', 'message' => 'Session marked as disconnected'];
        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function getSessionToken(string $sessionId): ?string
    {
        // First try cache
        $token = Cache::get("whatsapp_token_{$sessionId}");
        if ($token) {
            return $token;
        }

        // Try from session data
        $session = WhatsAppSession::find($sessionId);
        if ($session && $session->session_data && isset($session->session_data['token'])) {
            $token = $session->session_data['token'];
            Cache::put("whatsapp_token_{$sessionId}", $token, now()->addHours(24));
            return $token;
        }

        return null;
    }
}
```

### Message Sending Trait

```php
<?php

namespace App\Traits\WhatsApp;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait MessageSending
{
    public function sendTextMessage(string $sessionId, string $to, string $message): array
    {
        $token = Cache::get("whatsapp_token_{$sessionId}");

        if (!$token) {
            throw new \Exception("Session token not found for session {$sessionId}");
        }

        try {
            $messageData = [
                [
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/messages?sessionId={$sessionId}", $messageData);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('WhatsApp message sent', [
                    'session_id' => $sessionId,
                    'to' => $to,
                    'message_id' => $result[0]['messageId'] ?? null,
                ]);

                return $result;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'session_id' => $sessionId,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function sendMessage(WhatsAppSession $session, string $to, string $message, string $type = 'text'): void
    {
        // Store message history
        $this->storeMessageHistory($session->id, $to, $message, $type);

        // Queue message for sending
        SendWhatsAppMessageJob::dispatch($session->id, $to, $message, $type);

        Log::info('WhatsApp message queued for delivery', [
            'session_id' => $session->id,
            'to' => $to,
            'message_type' => $type,
        ]);
    }

    protected function storeMessageHistory(string $sessionId, string $to, string $message, string $type = 'text'): void
    {
        WhatsAppMessageHistory::create([
            'session_id' => $sessionId,
            'sender_user_id' => auth()->id(),
            'recipient_phone' => $to,
            'message_type' => $type,
            'message_content' => $message,
            'status' => WhatsAppMessageStatus::QUEUED,
        ]);
    }
}
```

## 5. Job for Message Processing

```php
<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $to,
        public string $message,
        public string $type = 'text'
    ) {}

    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $session = WhatsAppSession::find($this->sessionId);

            if (!$session || !$session->isConnected()) {
                throw new \Exception('WhatsApp session not connected');
            }

            $result = $whatsappService->sendTextMessage(
                $this->sessionId,
                $this->to,
                $this->message
            );

            // Update message history
            $messageHistory = WhatsAppMessageHistory::where('session_id', $this->sessionId)
                ->where('recipient_phone', $this->to)
                ->where('message_content', $this->message)
                ->where('status', 'queued')
                ->first();

            if ($messageHistory) {
                $messageHistory->markAsSent($result[0]['messageId'] ?? null);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message in job', [
                'session_id' => $this->sessionId,
                'to' => $this->to,
                'error' => $e->getMessage(),
            ]);

            // Update message history as failed
            $messageHistory = WhatsAppMessageHistory::where('session_id', $this->sessionId)
                ->where('recipient_phone', $this->to)
                ->where('message_content', $this->message)
                ->where('status', 'queued')
                ->first();

            if ($messageHistory) {
                $messageHistory->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }
}
```

## 6. HTTP Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsapp
    ) {}

    public function createSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:50|regex:/^[a-zA-Z0-9_-]+$/'
        ]);

        try {
            $result = $this->whatsapp->createSession($request->session_id);

            return response()->json([
                'success' => true,
                'session' => $result,
                'message' => 'Session created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSessionStatus(string $sessionId)
    {
        try {
            $status = $this->whatsapp->getSessionStatus($sessionId);

            return response()->json([
                'success' => true,
                'status' => $status,
                'is_connected' => $status['status'] === 'CONNECTED',
                'qr_code' => $status['qr'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendMessage(Request $request, string $sessionId)
    {
        $request->validate([
            'to' => 'required|string|regex:/^[0-9]+$/',
            'message' => 'required|string|max:4096'
        ]);

        try {
            $result = $this->whatsapp->sendTextMessage(
                $sessionId,
                $request->to,
                $request->message
            );

            return response()->json([
                'success' => true,
                'result' => $result,
                'message' => 'Message sent successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

## 7. User Interface Components

### QR Code Display Blade Component

```blade
{{-- resources/views/whatsapp/qr-code.blade.php --}}
<div class="space-y-4" @if(isset($status) && $status->canShowQrCode()) wire:poll.3s @endif>
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Scan QR Code to Connect WhatsApp
        </h3>
        @if($sessionName)
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Session: {{ $sessionName }}
            </p>
        @endif
    </div>

    @if($qrCode)
        <div class="flex justify-center">
            <div class="bg-white p-4 rounded-lg shadow-lg">
                <img src="{{ $qrCode }}" alt="WhatsApp QR Code"
                     class="w-64 h-64 mx-auto"
                     onerror="this.parentNode.innerHTML='<div class=\'w-64 h-64 flex items-center justify-center text-red-500\'>Failed to load QR code</div>'" />
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                              clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Instructions
                    </h4>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Open WhatsApp on your phone</li>
                            <li>Go to Settings → Linked Devices</li>
                            <li>Tap "Link a Device"</li>
                            <li>Scan this QR code</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <button type="button" onclick="refreshQrCode()"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                Refresh QR Code
            </button>
        </div>

        <script>
            function refreshQrCode() {
                window.location.reload();
            }
        </script>
    @else
        <div class="text-center py-8">
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                No QR Code Available
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Please start the session to generate a QR code
            </p>
        </div>
    @endif
</div>
```

### Admin Interface (Livewire Component)

```php
<?php

namespace App\Livewire;

use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Livewire\Component;

class WhatsAppManager extends Component
{
    public ?WhatsAppSession $session = null;
    public string $recipient = '';
    public string $message = '';
    public array $messages = [];

    protected $rules = [
        'recipient' => 'required|string|regex:/^[0-9]+$/',
        'message' => 'required|string|max:4096',
    ];

    public function mount()
    {
        $this->session = WhatsAppSession::getActiveSession();
        $this->loadMessages();
    }

    public function startSession()
    {
        try {
            if (!$this->session) {
                $this->session = WhatsAppSession::create([
                    'name' => 'Main Session',
                    'status' => 'disconnected',
                ]);
            }

            $whatsappService = app(WhatsAppService::class);
            $whatsappService->startSession($this->session);

            $this->session->refresh();
            session()->flash('message', 'Session started successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to start session: ' . $e->getMessage());
        }
    }

    public function sendMessage()
    {
        $this->validate();

        try {
            if (!$this->session || !$this->session->isConnected()) {
                throw new \Exception('WhatsApp session not connected');
            }

            $whatsappService = app(WhatsAppService::class);
            $whatsappService->sendMessage($this->session, $this->recipient, $this->message);

            $this->message = '';
            $this->loadMessages();
            session()->flash('message', 'Message queued successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send message: ' . $e->getMessage());
        }
    }

    public function disconnect()
    {
        try {
            if ($this->session) {
                $whatsappService = app(WhatsAppService::class);
                $whatsappService->deleteSession($this->session->id);
                $this->session->markAsDisconnected();
                $this->session->refresh();
            }

            session()->flash('message', 'Session disconnected successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to disconnect: ' . $e->getMessage());
        }
    }

    protected function loadMessages()
    {
        if ($this->session) {
            $this->messages = $this->session->messages()
                ->latest()
                ->limit(10)
                ->get()
                ->toArray();
        }
    }

    public function render()
    {
        return view('livewire.whatsapp-manager');
    }
}
```

### Livewire Blade Template

```blade
{{-- resources/views/livewire/whatsapp-manager.blade.php --}}
<div class="space-y-6">
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Session Status -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">WhatsApp Session Status</h3>

        @if ($session)
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      style="background-color: {{ $session->status->getColor() }}20; color: {{ $session->status->getColor() }}">
                    {{ $session->status->getLabel() }}
                </span>

                @if ($session->isConnected())
                    <button wire:click="disconnect"
                            class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                        Disconnect
                    </button>
                @else
                    <button wire:click="startSession"
                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                        Start Session
                    </button>
                @endif
            </div>

            @if ($session->status->canShowQrCode() && $session->qr_code)
                <div class="mt-4">
                    @include('whatsapp.qr-code', [
                        'qrCode' => $session->getQrCodeData(),
                        'sessionName' => $session->name,
                        'status' => $session->status
                    ])
                </div>
            @endif
        @else
            <button wire:click="startSession"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Create Session
            </button>
        @endif
    </div>

    <!-- Send Message -->
    @if ($session && $session->isConnected())
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Send Message</h3>

            <form wire:submit.prevent="sendMessage" class="space-y-4">
                <div>
                    <label for="recipient" class="block text-sm font-medium text-gray-700">
                        Recipient Phone Number
                    </label>
                    <input wire:model="recipient"
                           type="text"
                           id="recipient"
                           placeholder="966501234567"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    @error('recipient') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">
                        Message
                    </label>
                    <textarea wire:model="message"
                              id="message"
                              rows="3"
                              placeholder="Enter your message here..."
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    @error('message') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <button type="submit"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Send Message
                </button>
            </form>
        </div>
    @endif

    <!-- Recent Messages -->
    @if (!empty($messages))
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Messages</h3>

            <div class="space-y-3">
                @foreach ($messages as $msg)
                    <div class="border-l-4 border-gray-200 pl-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    To: {{ $msg['recipient_phone'] }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    {{ $msg['message_content'] }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $msg['status']->getColor() }}20; color: {{ $msg['status']->getColor() }}">
                                {{ $msg['status']->getLabel() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $msg['created_at'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
```

## 8. Configuration

### WhatsApp Configuration File

```php
<?php

// config/whatsapp.php

return [
    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:3000'),
    'api_token' => env('WHATSAPP_API_TOKEN'),
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
    'rate_limit' => [
        'messages_per_minute' => env('WHATSAPP_RATE_LIMIT_PER_MINUTE', 10),
        'burst_limit' => env('WHATSAPP_BURST_LIMIT', 50),
    ],
    'session' => [
        'timeout_hours' => env('WHATSAPP_SESSION_TIMEOUT_HOURS', 24),
        'max_retry_attempts' => env('WHATSAPP_MAX_RETRY_ATTEMPTS', 3),
    ],
];
```

### Environment Variables

```bash
# .env
WHATSAPP_API_URL=http://localhost:3000
WHATSAPP_API_TOKEN=your_master_api_key
WHATSAPP_WEBHOOK_URL=https://yourdomain.com/webhooks/whatsapp
WHATSAPP_RATE_LIMIT_PER_MINUTE=10
WHATSAPP_BURST_LIMIT=50
WHATSAPP_SESSION_TIMEOUT_HOURS=24
WHATSAPP_MAX_RETRY_ATTEMPTS=3
```

## 9. Routes

```php
<?php

// routes/web.php
use App\Http\Controllers\WhatsAppController;

Route::prefix('whatsapp')->group(function () {
    Route::post('/sessions', [WhatsAppController::class, 'createSession']);
    Route::get('/sessions/{sessionId}/status', [WhatsAppController::class, 'getSessionStatus']);
    Route::post('/sessions/{sessionId}/messages', [WhatsAppController::class, 'sendMessage']);
    Route::delete('/sessions/{sessionId}', [WhatsAppController::class, 'deleteSession']);
});

// Admin interface
Route::get('/admin/whatsapp', function () {
    return view('admin.whatsapp');
})->middleware(['auth']);
```

## 10. Queue Configuration

Add to your `config/queue.php`:

```php
'connections' => [
    // ... other connections

    'whatsapp' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'whatsapp',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

Run queue worker:
```bash
php artisan queue:work --queue=whatsapp
```

## Key Features

1. **Session Management**: Complete WhatsApp Web session lifecycle
2. **QR Code Authentication**: Real-time QR code generation and display
3. **Message Queue**: Reliable message delivery with retry logic
4. **Status Tracking**: Real-time connection and message status monitoring
5. **Admin Interface**: User-friendly management dashboard
6. **Rate Limiting**: Built-in rate limiting for API calls
7. **Error Handling**: Comprehensive error logging and user feedback
8. **Multi-tenancy Ready**: Architecture supports multiple organizations
9. **Media Support**: Framework for images, documents, and other media
10. **Webhook Support**: Ready for incoming message webhooks

## Deployment Considerations

1. **WhatsApp API Service**: Deploy a WhatsApp Web API service (like whatsapp-web.js)
2. **Queue Workers**: Ensure queue workers are running for message processing
3. **Session Persistence**: Use Redis or database for session storage
4. **Rate Limiting**: Respect WhatsApp's rate limits
5. **Monitoring**: Implement monitoring for session health and message delivery
6. **Security**: Secure API endpoints and validate all inputs
7. **Backup**: Regular backup of session data and message history

This implementation provides a solid foundation for WhatsApp integration in any Laravel application, with room for customization based on specific business requirements.