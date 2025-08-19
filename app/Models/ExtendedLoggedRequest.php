<?php

namespace App\Models;

use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

class ExtendedLoggedRequest extends LoggedRequest
{
    protected $table = 'logged_requests';

    protected $fillable = [
        'ip_address_id',
        'country_code',
        'method',
        'content_length',
        'status_code',
        'user_agent_id',
        'mime_type_id',
        'url_id',
        'referer_url_id',
        'origin_url_id',
        'user_id',
        'is_bot_by_frequency',
        'is_bot_by_user_agent',
        'is_bot_by_parameters',
        'bot_detection_metadata',
        'bot_analyzed_at',
    ];

    protected $casts = [
        'ip_address_id' => 'integer',
        'country_code' => 'string',
        'content_length' => 'integer',
        'status_code' => 'integer',
        'user_agent_id' => 'integer',
        'mime_type_id' => 'integer',
        'url_id' => 'integer',
        'referer_url_id' => 'integer',
        'origin_url_id' => 'integer',
        'user_id' => 'integer',
        'is_bot_by_frequency' => 'boolean',
        'is_bot_by_user_agent' => 'boolean',
        'is_bot_by_parameters' => 'boolean',
        'bot_analyzed_at' => 'datetime',
    ];
}
