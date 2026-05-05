<?php

return [
    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:8080'),
    'api_key' => env('WHATSAPP_API_KEY'),
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    // Anti-ban: Random delay range between messages (in seconds)
    'delay_min' => env('WHATSAPP_DELAY_MIN', 13),
    'delay_max' => env('WHATSAPP_DELAY_MAX', 20),

    // Inter-group delay: random pause between each group's batch of messages (in seconds)
    'group_delay_min' => env('WHATSAPP_GROUP_DELAY_MIN', 60),
    'group_delay_max' => env('WHATSAPP_GROUP_DELAY_MAX', 120),

    // Rate limit: max messages per minute per session (safety net)
    'messages_per_minute' => env('WHATSAPP_MESSAGES_PER_MINUTE', 10),

    // Typing simulation: passes Evolution API's `delay` (ms) on sendText/sendMedia.
    // Per the Evolution v2 spec, `delay` is the "presence time in ms before sending" — the
    // server displays the "composing" indicator for that duration, then delivers the message.
    // Computed as (per_char_ms * message length) + random jitter, clamped to [min_ms, max_ms].
    // Runs server-side, so the queue worker is not blocked.
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
