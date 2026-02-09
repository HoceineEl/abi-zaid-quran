<?php

return [
    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:3000'),
    'api_token' => env('WHATSAPP_API_TOKEN'),
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    // Anti-ban: Random delay range between messages (in seconds)
    'delay_min' => env('WHATSAPP_DELAY_MIN', 8),
    'delay_max' => env('WHATSAPP_DELAY_MAX', 20),

    // Rate limit: max messages per minute per session (safety net)
    'messages_per_minute' => env('WHATSAPP_MESSAGES_PER_MINUTE', 10),

    'session' => [
        'timeout_hours' => env('WHATSAPP_SESSION_TIMEOUT_HOURS', 24),
        'max_retry_attempts' => env('WHATSAPP_MAX_RETRY_ATTEMPTS', 3),
    ],
];
