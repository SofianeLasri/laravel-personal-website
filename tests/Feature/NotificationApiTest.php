<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting notifications for authenticated user
     */
    public function test_get_notifications_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        // Create notifications for this user
        $notification1 = Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'Test Error 1',
            'message' => 'Error message 1',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $notification2 = Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Test Info',
            'message' => 'Info message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        // Create notification for another user (should not be returned)
        $otherUser = User::factory()->create();
        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Other User Notification',
            'message' => 'Should not see this',
            'is_read' => false,
            'user_id' => $otherUser->id,
        ]);

        // Make request as authenticated user
        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
        ]);

        $data = $response->json('data');

        // Check if it's paginated or not
        if (isset($data['data'])) {
            // Paginated response
            $notifications = $data['data'];
        } else {
            // Non-paginated response
            $notifications = $data;
        }

        $this->assertCount(2, $notifications);

        // Check we have the right notifications
        $ids = collect($notifications)->pluck('id')->toArray();
        $this->assertContains($notification1->id, $ids);
        $this->assertContains($notification2->id, $ids);
    }

    /**
     * Test getting unread count
     */
    public function test_get_unread_count(): void
    {
        $user = User::factory()->create();

        // Create 3 unread and 1 read notification
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Notification $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Read Notification',
            'message' => 'This is read',
            'is_read' => true,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications/unread-count');

        $response->assertOk();
        $response->assertJson([
            'count' => 3,
        ]);
    }

    /**
     * Test marking notification as read
     */
    public function test_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Test Notification',
            'message' => 'Test message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/dashboard/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Check notification is marked as read
        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * Test mark all as read
     */
    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Notification $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)
            ->putJson('/dashboard/api/notifications/read-all');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 3,
        ]);

        // Check all are marked as read
        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unreadCount);
    }
}
