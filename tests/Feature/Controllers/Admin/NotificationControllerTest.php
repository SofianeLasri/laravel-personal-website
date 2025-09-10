<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\NotificationController;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NotificationController::class)]
class NotificationControllerTest extends TestCase
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

    /**
     * Test getting notifications with pagination
     */
    public function test_get_notifications_with_pagination(): void
    {
        $user = User::factory()->create();

        // Create 25 notifications
        for ($i = 0; $i < 25; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Notification $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        // Test with default pagination (20 per page)
        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications');

        $response->assertOk();
        $data = $response->json('data');
        
        // Check pagination structure
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('current_page', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertCount(20, $data['data']);
        $this->assertEquals(25, $data['total']);

        // Test with custom per_page
        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications?per_page=10');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(10, $data['data']);
    }

    /**
     * Test getting all notifications without pagination
     */
    public function test_get_all_notifications_without_pagination(): void
    {
        $user = User::factory()->create();

        // Create 30 notifications
        for ($i = 0; $i < 30; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Notification $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        // Test with per_page=0 (no pagination)
        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications?per_page=0');

        $response->assertOk();
        $data = $response->json('data');
        
        // Should return all notifications as array
        $this->assertIsArray($data);
        $this->assertCount(30, $data);
    }

    /**
     * Test getting only unread notifications
     */
    public function test_get_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        // Create 3 unread and 2 read notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Unread $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Read $i",
                'message' => "Message $i",
                'is_read' => true,
                'read_at' => now(),
                'user_id' => $user->id,
            ]);
        }

        // Test with include_read=false
        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications?include_read=false&per_page=0');

        $response->assertOk();
        $notifications = $response->json('data');
        
        $this->assertCount(3, $notifications);
        foreach ($notifications as $notification) {
            $this->assertFalse($notification['is_read']);
        }
    }

    /**
     * Test marking non-existent notification as read returns 404
     */
    public function test_mark_non_existent_notification_as_read_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/dashboard/api/notifications/99999/read');

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Notification not found',
        ]);
    }

    /**
     * Test marking other user's notification as read returns 404
     */
    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Other User Notification',
            'message' => 'Test message',
            'is_read' => false,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/dashboard/api/notifications/{$notification->id}/read");

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Notification not found',
        ]);

        // Verify notification is still unread
        $this->assertFalse($notification->fresh()->is_read);
    }

    /**
     * Test destroy notification
     */
    public function test_destroy_notification(): void
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
            ->deleteJson("/dashboard/api/notifications/{$notification->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Verify notification is deleted
        $this->assertNull(Notification::find($notification->id));
    }

    /**
     * Test destroy non-existent notification returns 404
     */
    public function test_destroy_non_existent_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson('/dashboard/api/notifications/99999');

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Notification not found',
        ]);
    }

    /**
     * Test cannot destroy other user's notification
     */
    public function test_cannot_destroy_other_users_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Other User Notification',
            'message' => 'Test message',
            'is_read' => false,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/dashboard/api/notifications/{$notification->id}");

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Notification not found',
        ]);

        // Verify notification still exists
        $this->assertNotNull(Notification::find($notification->id));
    }

    /**
     * Test clear all non-persistent notifications
     */
    public function test_clear_all_non_persistent_notifications(): void
    {
        $user = User::factory()->create();

        // Create 3 non-persistent and 2 persistent notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Non-persistent $i",
                'message' => "Message $i",
                'is_read' => false,
                'is_persistent' => false,
                'user_id' => $user->id,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'warning',
                'title' => "Persistent $i",
                'message' => "Message $i",
                'is_read' => false,
                'is_persistent' => true,
                'user_id' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)
            ->deleteJson('/dashboard/api/notifications/clear');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 3,
        ]);

        // Verify only persistent notifications remain
        $remainingNotifications = Notification::where('user_id', $user->id)->get();
        $this->assertCount(2, $remainingNotifications);
        foreach ($remainingNotifications as $notification) {
            $this->assertTrue($notification->is_persistent);
        }
    }

    /**
     * Test store notification with valid data
     */
    public function test_store_notification_with_valid_data(): void
    {
        $user = User::factory()->create();

        $data = [
            'type' => 'success',
            'title' => 'Test Success Notification',
            'message' => 'This is a test success message',
            'data' => ['key' => 'value'],
            'source' => 'test_source',
            'action_url' => 'https://example.com/action',
            'action_label' => 'Take Action',
            'is_persistent' => true,
            'expires_at' => now()->addDays(7)->toDateTimeString(),
        ];

        $response = $this->actingAs($user)
            ->postJson('/dashboard/api/notifications', $data);

        $response->assertCreated();
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'notification' => [
                'id',
                'type',
                'title',
                'message',
                'data',
                'source',
                'action_url',
                'action_label',
                'is_persistent',
                'expires_at',
                'user_id',
            ],
        ]);

        $notification = $response->json('notification');
        $this->assertEquals($user->id, $notification['user_id']);
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Test Success Notification', $notification['title']);
    }

    /**
     * Test store notification with minimal required data
     */
    public function test_store_notification_with_minimal_data(): void
    {
        $user = User::factory()->create();

        $data = [
            'type' => 'info',
            'title' => 'Minimal Notification',
            'message' => 'Minimal message',
        ];

        $response = $this->actingAs($user)
            ->postJson('/dashboard/api/notifications', $data);

        $response->assertCreated();
        $response->assertJson([
            'success' => true,
        ]);

        $notification = $response->json('notification');
        $this->assertEquals($user->id, $notification['user_id']);
        $this->assertEquals('info', $notification['type']);
    }

    /**
     * Test store notification with invalid type
     */
    public function test_store_notification_with_invalid_type(): void
    {
        $user = User::factory()->create();

        $data = [
            'type' => 'invalid_type',
            'title' => 'Test Notification',
            'message' => 'Test message',
        ];

        $response = $this->actingAs($user)
            ->postJson('/dashboard/api/notifications', $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    /**
     * Test store notification with missing required fields
     */
    public function test_store_notification_with_missing_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/dashboard/api/notifications', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type', 'title', 'message']);
    }

    /**
     * Test store notification with invalid URL
     */
    public function test_store_notification_with_invalid_url(): void
    {
        $user = User::factory()->create();

        $data = [
            'type' => 'info',
            'title' => 'Test Notification',
            'message' => 'Test message',
            'action_url' => 'not-a-valid-url',
        ];

        $response = $this->actingAs($user)
            ->postJson('/dashboard/api/notifications', $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['action_url']);
    }

    /**
     * Test notifications are filtered by expired date
     */
    public function test_notifications_filtered_by_expired_date(): void
    {
        $user = User::factory()->create();

        // Create expired notification
        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Expired Notification',
            'message' => 'This is expired',
            'is_read' => false,
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        // Create active notification
        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Active Notification',
            'message' => 'This is active',
            'is_read' => false,
            'user_id' => $user->id,
            'expires_at' => now()->addDay(),
        ]);

        // Create notification without expiry
        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'No Expiry Notification',
            'message' => 'This has no expiry',
            'is_read' => false,
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications?per_page=0');

        $response->assertOk();
        $notifications = $response->json('data');
        
        // Should only return non-expired notifications
        $this->assertCount(2, $notifications);
        $titles = collect($notifications)->pluck('title')->toArray();
        $this->assertContains('Active Notification', $titles);
        $this->assertContains('No Expiry Notification', $titles);
        $this->assertNotContains('Expired Notification', $titles);
    }

    /**
     * Test notifications are ordered by created_at desc
     */
    public function test_notifications_ordered_by_created_at_desc(): void
    {
        $user = User::factory()->create();

        // Create notifications with specific timestamps
        $notification1 = new Notification([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Old Notification',
            'message' => 'Created first',
            'is_read' => false,
            'user_id' => $user->id,
        ]);
        $notification1->created_at = now()->subHours(2);
        $notification1->save();

        $notification2 = new Notification([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'New Notification',
            'message' => 'Created last',
            'is_read' => false,
            'user_id' => $user->id,
        ]);
        $notification2->created_at = now();
        $notification2->save();

        $notification3 = new Notification([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Middle Notification',
            'message' => 'Created middle',
            'is_read' => false,
            'user_id' => $user->id,
        ]);
        $notification3->created_at = now()->subHour();
        $notification3->save();

        $response = $this->actingAs($user)
            ->getJson('/dashboard/api/notifications?per_page=0');

        $response->assertOk();
        $notifications = $response->json('data');
        
        $this->assertCount(3, $notifications);
        // Check order: newest first
        $this->assertEquals($notification2->id, $notifications[0]['id']);
        $this->assertEquals($notification3->id, $notifications[1]['id']);
        $this->assertEquals($notification1->id, $notifications[2]['id']);
    }

    /**
     * Test unauthenticated access returns 401
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/dashboard/api/notifications');
        $response->assertUnauthorized();

        $response = $this->getJson('/dashboard/api/notifications/unread-count');
        $response->assertUnauthorized();

        $response = $this->putJson('/dashboard/api/notifications/1/read');
        $response->assertUnauthorized();

        $response = $this->putJson('/dashboard/api/notifications/read-all');
        $response->assertUnauthorized();

        $response = $this->deleteJson('/dashboard/api/notifications/1');
        $response->assertUnauthorized();

        $response = $this->deleteJson('/dashboard/api/notifications/clear-all');
        $response->assertUnauthorized();

        $response = $this->postJson('/dashboard/api/notifications', []);
        $response->assertUnauthorized();
    }

    /**
     * Test marking already read notification as read
     */
    public function test_mark_already_read_notification_as_read(): void
    {
        $user = User::factory()->create();
        $readAt = now()->subHour();

        $notification = Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Already Read Notification',
            'message' => 'Test message',
            'is_read' => true,
            'read_at' => $readAt,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/dashboard/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Check read_at hasn't changed
        $this->assertEquals(
            $readAt->format('Y-m-d H:i:s'),
            $notification->fresh()->read_at->format('Y-m-d H:i:s')
        );
    }

    /**
     * Test clear all when user has only persistent notifications
     */
    public function test_clear_all_with_only_persistent_notifications(): void
    {
        $user = User::factory()->create();

        // Create only persistent notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'warning',
                'title' => "Persistent $i",
                'message' => "Message $i",
                'is_read' => false,
                'is_persistent' => true,
                'user_id' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)
            ->deleteJson('/dashboard/api/notifications/clear');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 0,
        ]);

        // Verify all notifications still exist
        $this->assertCount(3, Notification::where('user_id', $user->id)->get());
    }
}
