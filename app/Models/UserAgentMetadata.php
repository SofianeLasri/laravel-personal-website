<?php

namespace App\Models;

use Database\Factories\UserAgentMetadataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

/**
 * @property int $id
 * @property int $user_agent_id
 * @property bool $is_bot
 * @property mixed $use_factory
 * @property int $user_agents_count
 * @property-read UserAgent $userAgent
 */
class UserAgentMetadata extends Model
{
    /** @use HasFactory<UserAgentMetadataFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_agent_id',
        'is_bot',
    ];

    public function userAgent(): BelongsTo
    {
        return $this->belongsTo(UserAgent::class);
    }

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
        ];
    }
}
