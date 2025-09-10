<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $type
 * @property string $severity
 * @property string $title
 * @property string $message
 * @property array<string, mixed>|null $data
 * @property array<string, mixed>|null $context
 * @property string|null $source
 * @property string|null $action_url
 * @property string|null $action_label
 * @property bool $is_read
 * @property bool $is_persistent
 * @property Carbon|null $read_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'message',
        'data',
        'context',
        'source',
        'action_url',
        'action_label',
        'is_read',
        'is_persistent',
        'read_at',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'context' => 'array',
        'is_read' => 'boolean',
        'is_persistent' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Notification types
     */
    public const TYPE_SUCCESS = 'success';

    public const TYPE_ERROR = 'error';

    public const TYPE_WARNING = 'warning';

    public const TYPE_INFO = 'info';

    /**
     * Notification sources
     */
    public const SOURCE_AI_PROVIDER = 'ai_provider';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_USER = 'user';

    /**
     * Get the user that owns the notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope a query to only include notifications for a specific user.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include non-expired notifications.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Check if the notification has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the notification icon based on type.
     */
    public function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => 'check-circle',
            self::TYPE_ERROR => 'x-circle',
            self::TYPE_WARNING => 'alert-triangle',
            self::TYPE_INFO => 'info',
            default => 'bell',
        };
    }

    /**
     * Get the notification color class based on type.
     */
    public function getColorClass(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => 'text-green-500',
            self::TYPE_ERROR => 'text-red-500',
            self::TYPE_WARNING => 'text-yellow-500',
            self::TYPE_INFO => 'text-blue-500',
            default => 'text-gray-500',
        };
    }
}
