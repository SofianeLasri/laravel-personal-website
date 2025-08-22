<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $includeRead = $request->boolean('include_read', true);
        $perPage = $request->integer('per_page', 20);

        $query = Notification::forUser(Auth::id())
            ->notExpired()
            ->orderBy('created_at', 'desc');

        if (!$includeRead) {
            $query->unread();
        }

        if ($perPage > 0) {
            $notifications = $query->paginate($perPage);
        } else {
            $notifications = $query->get();
        }

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount(): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount(Auth::id());

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::forUser(Auth::id())->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $notification->markAsRead();

        return response()->json([
            'success' => $success,
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead(Auth::id());

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = Notification::forUser(Auth::id())->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $notification->delete();

        return response()->json([
            'success' => $success,
        ]);
    }

    /**
     * Clear all non-persistent notifications
     */
    public function clearAll(): JsonResponse
    {
        $count = Notification::forUser(Auth::id())
            ->where('is_persistent', false)
            ->delete();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Create a new notification (for testing purposes)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:success,error,warning,info',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'source' => 'nullable|string|max:255',
            'action_url' => 'nullable|url',
            'action_label' => 'nullable|string|max:255',
            'is_persistent' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $notification = $this->notificationService->create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ], 201);
    }

    /**
     * Stream notifications using Server-Sent Events
     */
    public function stream(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () {
            while (true) {
                $notifications = $this->notificationService->getUserNotifications(Auth::id());

                foreach ($notifications as $notification) {
                    echo "data: " . json_encode($notification) . "\n\n";
                }

                ob_flush();
                flush();

                // Sleep for 30 seconds before next check
                sleep(30);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}