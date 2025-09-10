<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_request_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'model',
        'endpoint',
        'status',
        'http_status_code',
        'error_message',
        'system_prompt',
        'user_prompt',
        'response',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'response_time',
        'estimated_cost',
        'fallback_provider',
        'metadata',
        'cached',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'response' => 'array',
        'metadata' => 'array',
        'cached' => 'boolean',
        'response_time' => 'decimal:3',
        'estimated_cost' => 'decimal:6',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'http_status_code' => 'integer',
    ];

    /**
     * Get truncated system prompt for display
     *
     * @param  int  $length  Maximum length
     */
    public function getTruncatedSystemPrompt(int $length = 100): string
    {
        return strlen($this->system_prompt) > $length
            ? substr($this->system_prompt, 0, $length).'...'
            : $this->system_prompt;
    }

    /**
     * Get truncated user prompt for display
     *
     * @param  int  $length  Maximum length
     */
    public function getTruncatedUserPrompt(int $length = 100): string
    {
        return strlen($this->user_prompt) > $length
            ? substr($this->user_prompt, 0, $length).'...'
            : $this->user_prompt;
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'error' => 'red',
            'timeout' => 'orange',
            'fallback' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Check if request was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success' || $this->status === 'fallback';
    }
}
