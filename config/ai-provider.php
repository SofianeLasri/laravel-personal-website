<?php

return [
    'selected-provider' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
            'api-key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max-tokens' => env('OPENAI_MAX_TOKENS', 256),
        ],
        'anthropic' => [
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1/complete'),
            'api-key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'max-tokens' => env('ANTHROPIC_MAX_TOKENS', 256),
        ],
    ],
];
