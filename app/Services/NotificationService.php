<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new notification
     *
     * @param array{
     *     user_id?: int|null,
     *     type: string,
     *     title: string,
     *     message: string,
     *     data?: array|null,
     *     source?: string|null,
     *     action_url?: string|null,
     *     action_label?: string|null,
     *     is_persistent?: bool,
     *     expires_at?: string|null
     * } $data
     */
    public function create(array $data): Notification
    {
        $notification = Notification::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'source' => $data['source'] ?? Notification::SOURCE_SYSTEM,
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'is_persistent' => $data['is_persistent'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        Log::info('Notification created', [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'user_id' => $notification->user_id,
        ]);

        return $notification;
    }

    /**
     * Create a success notification
     *
     * @param array<string, mixed>|null $data
     */
    public function success(string $title, string $message, ?array $data = null): Notification
    {
        return $this->create([
            'type' => Notification::TYPE_SUCCESS,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Create an error notification
     *
     * @param array<string, mixed>|null $data
     */
    public function error(string $title, string $message, ?array $data = null): Notification
    {
        return $this->create([
            'type' => Notification::TYPE_ERROR,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Create a warning notification
     *
     * @param array<string, mixed>|null $data
     */
    public function warning(string $title, string $message, ?array $data = null): Notification
    {
        return $this->create([
            'type' => Notification::TYPE_WARNING,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Create an info notification
     *
     * @param array<string, mixed>|null $data
     */
    public function info(string $title, string $message, ?array $data = null): Notification
    {
        return $this->create([
            'type' => Notification::TYPE_INFO,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Get notifications for a user
     *
     * @return Collection<int, Notification>
     */
    public function getUserNotifications(?int $userId = null, bool $includeRead = false): Collection
    {
        $userId = $userId ?? auth()->id();

        $query = Notification::forUser($userId)
            ->notExpired()
            ->orderBy('created_at', 'desc');

        if (!$includeRead) {
            $query->unread();
        }

        return $query->get();
    }

    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount(?int $userId = null): int
    {
        $userId = $userId ?? auth()->id();

        return Notification::forUser($userId)
            ->unread()
            ->notExpired()
            ->count();
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if (!$notification) {
            Log::warning('Notification not found for marking as read', ['id' => $notificationId]);

            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(?int $userId = null): int
    {
        $userId = $userId ?? auth()->id();

        return Notification::forUser($userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Delete a notification
     */
    public function delete(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if (!$notification) {
            Log::warning('Notification not found for deletion', ['id' => $notificationId]);

            return false;
        }

        return (bool)$notification->delete();
    }

    /**
     * Delete all read notifications for a user
     */
    public function deleteReadNotifications(?int $userId = null): int
    {
        $userId = $userId ?? auth()->id();

        return Notification::forUser($userId)
            ->read()
            ->where('is_persistent', false)
            ->delete();
    }

    /**
     * Clean up expired notifications
     */
    public function cleanupExpired(): int
    {
        $count = Notification::where('expires_at', '<', now())->delete();

        if ($count > 0) {
            Log::info('Cleaned up expired notifications', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Create a notification for AI provider events
     *
     * @param array<string, mixed>|null $data
     */
    public function createAiProviderNotification(string $type, string $title, string $message, ?array $data = null): Notification
    {
        return $this->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'source' => Notification::SOURCE_AI_PROVIDER,
        ]);
    }

    /**
     * Create a notification with action
     *
     * @param array<string, mixed>|null $data
     */
    public function createWithAction(
        string $type,
        string $title,
        string $message,
        string $actionUrl,
        string $actionLabel,
        ?array $data = null
    ): Notification
    {
        return $this->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'data' => $data,
        ]);
    }

    /**
     * Broadcast a notification to all users
     *
     * @param array<string, mixed>|null $data
     */
    public function broadcast(string $type, string $title, string $message, ?array $data = null): int
    {
        $users = User::all();
        $count = 0;

        foreach ($users as $user) {
            $this->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'source' => Notification::SOURCE_SYSTEM,
            ]);
            $count++;
        }

        Log::info('Broadcast notification sent', [
            'type' => $type,
            'title' => $title,
            'users_count' => $count,
        ]);

        return $count;
    }
}
