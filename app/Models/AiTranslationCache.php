<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTranslationCache extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_translation_cache';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cache_key',
        'provider',
        'system_prompt',
        'user_prompt',
        'response',
        'hits',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'response' => 'array',
        'hits' => 'integer',
    ];

    /**
     * Increment the hit counter for this cache entry
     *
     * @return void
     */
    public function incrementHits(): void
    {
        $this->increment('hits');
    }

    /**
     * Check if the cache entry is expired
     *
     * @param int $ttlInSeconds The TTL in seconds
     * @return bool
     */
    public function isExpired(int $ttlInSeconds): bool
    {
        return $this->created_at->addSeconds($ttlInSeconds)->isPast();
    }
}
