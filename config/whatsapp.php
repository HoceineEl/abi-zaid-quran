<?php

return [
    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:8080'),
    'api_key' => env('WHATSAPP_API_KEY'),
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    // Anti-ban: Random delay range between messages (in seconds)
    'delay_min' => env('WHATSAPP_DELAY_MIN', 5),
    'delay_max' => env('WHATSAPP_DELAY_MAX', 10),

    // Rate limit: max messages per minute per session (safety net)
    'messages_per_minute' => env('WHATSAPP_MESSAGES_PER_MINUTE', 10),

    'session' => [
        'timeout_hours' => env('WHATSAPP_SESSION_TIMEOUT_HOURS', 24),
        'max_retry_attempts' => env('WHATSAPP_MAX_RETRY_ATTEMPTS', 3),
    ],

    'automation' => [
        'evening_time' => env('WHATSAPP_AUTOMATION_EVENING_TIME', '20:00'),
        'default_close_time' => env('WHATSAPP_AUTOMATION_DEFAULT_CLOSE_TIME', '23:00:00'),
    ],
];
