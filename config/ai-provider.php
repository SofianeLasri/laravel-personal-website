<?php

return [
    'selected-provider' => env('AI_PROVIDER', 'openai'),

    'fallback' => [
        'enabled' => env('AI_FALLBACK_ENABLED', true),
        'priority' => explode(',', env('AI_FALLBACK_PRIORITY', 'openai,anthropic')),
        'health_check_ttl' => env('AI_HEALTH_CHECK_TTL', 300), // 5 minutes
    ],

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 2592000), // 30 days in seconds
        'max_size' => env('AI_CACHE_MAX_SIZE', 1000), // Maximum number of cache entries
    ],

    'monitoring' => [
        'log_requests' => env('AI_LOG_REQUESTS', true),
        'calculate_costs' => env('AI_CALCULATE_COSTS', true),
        'notification_threshold' => [
            'errors_per_hour' => env('AI_ERROR_THRESHOLD', 5),
            'daily_cost_usd' => env('AI_COST_THRESHOLD', 10),
            'response_time_seconds' => env('AI_RESPONSE_TIME_THRESHOLD', 30),
        ],
    ],
    
    'providers' => [
        'openai' => [
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
            'api-key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max-tokens' => env('OPENAI_MAX_TOKENS', 4096),
            'pricing' => [
                'input_per_1k' => 0.00015,
                'output_per_1k' => 0.0006,
            ],
        ],
        'anthropic' => [
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1/messages'),
            'api-key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'max-tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
            'pricing' => [
                'input_per_1k' => 0.003,
                'output_per_1k' => 0.015,
            ],
        ],
    ],
];
