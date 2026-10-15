<?php

return [
    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:3000'),
    'username' => env('WHATSAPP_API_USERNAME'),
    'password' => env('WHATSAPP_API_PASSWORD'),
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    // Anti-ban: Random delay range between messages (in seconds)
    'delay_min' => env('WHATSAPP_DELAY_MIN', 15),
    'delay_max' => env('WHATSAPP_DELAY_MAX', 60),

    // Inter-group delay: random pause between each group's batch of messages (in seconds)
    'group_delay_min' => env('WHATSAPP_GROUP_DELAY_MIN', 60),
    'group_delay_max' => env('WHATSAPP_GROUP_DELAY_MAX', 120),

    // Rate limit: max messages per minute per session (safety net)
    'messages_per_minute' => env('WHATSAPP_MESSAGES_PER_MINUTE', 4),

    // Typing simulation: shows the "composing" indicator for (per_char_ms * length) + jitter,
    // clamped to [min_ms, max_ms], before each send. Runs inside the queue worker.
    'typing' => [
        'enabled' => env('WHATSAPP_TYPING_ENABLED', true),
        'min_ms' => env('WHATSAPP_TYPING_MIN_MS', 1200),
        'max_ms' => env('WHATSAPP_TYPING_MAX_MS', 5000),
        'per_char_ms' => env('WHATSAPP_TYPING_PER_CHAR_MS', 35),
        'jitter_ms' => env('WHATSAPP_TYPING_JITTER_MS', 350),
    ],

    'session' => [
        'timeout_hours' => env('WHATSAPP_SESSION_TIMEOUT_HOURS', 24),
        'max_retry_attempts' => env('WHATSAPP_MAX_RETRY_ATTEMPTS', 3),
    ],
];
