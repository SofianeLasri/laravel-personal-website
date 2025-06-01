<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

class UserAgentMetadata extends Model
{
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
