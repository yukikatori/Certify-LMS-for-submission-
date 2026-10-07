<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', false),

    'daily_message_limit' => (int) env('AI_CHAT_DAILY_MESSAGE_LIMIT', 50),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 1),
    ],
];
