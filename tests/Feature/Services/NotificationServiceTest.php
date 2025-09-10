<?php

namespace Tests\Feature\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NotificationService::class)]
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
    }

    #[Test]
    public function it_creates_notification_with_all_parameters(): void
    {
        $user = User::factory()->create();
        
        $data = [
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Test Notification',
            'message' => 'This is a test message',
            'data' => ['key' => 'value'],
            'source' => 'system',
            'action_url' => 'https://example.com',
            'action_label' => 'View',
            'is_persistent' => true,
            'expires_at' => now()->addDays(7)->toDateTimeString(),
        ];

        Log::shouldReceive('info')
            ->once()
            ->with('Notification created', \Mockery::type('array'));

        $notification = $this->service->create($data);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($user->id, $notification->user_id);
        $this->assertEquals('info', $notification->type);
        $this->assertEquals('info', $notification->severity);
        $this->assertEquals('Test Notification', $notification->title);
        $this->assertEquals('This is a test message', $notification->message);
        $this->assertEquals(['key' => 'value'], $notification->data);
        $this->assertEquals('system', $notification->source);
        $this->assertEquals('https://example.com', $notification->action_url);
        $this->assertEquals('View', $notification->action_label);
        $this->assertTrue($notification->is_persistent);
        $this->assertNotNull($notification->expires_at);
    }

    #[Test]
    public function it_creates_notification_with_minimal_parameters(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $data = [
            'type' => 'info',
            'title' => 'Minimal Notification',
            'message' => 'Minimal message',
        ];

        Log::shouldReceive('info')->once();

        $notification = $this->service->create($data);

        $this->assertEquals($user->id, $notification->user_id);
        $this->assertEquals('info', $notification->type);
        $this->assertEquals('Minimal Notification', $notification->title);
        $this->assertEquals('Minimal message', $notification->message);
        $this->assertEquals(Notification::SOURCE_SYSTEM, $notification->source);
        $this->assertFalse($notification->is_persistent);
        $this->assertNull($notification->expires_at);
    }

    #[Test]
    public function it_creates_success_notification(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->success(
            'Success Title',
            'Success message',
            ['extra' => 'data']
        );

        $this->assertEquals(Notification::TYPE_SUCCESS, $notification->type);
        $this->assertEquals('Success Title', $notification->title);
        $this->assertEquals('Success message', $notification->message);
        $this->assertEquals(['extra' => 'data'], $notification->data);
    }

    #[Test]
    public function it_creates_error_notification(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->error(
            'Error Title',
            'Error message',
            ['error_code' => 500]
        );

        $this->assertEquals(Notification::TYPE_ERROR, $notification->type);
        $this->assertEquals('Error Title', $notification->title);
        $this->assertEquals('Error message', $notification->message);
        $this->assertEquals(['error_code' => 500], $notification->data);
    }

    #[Test]
    public function it_creates_warning_notification(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->warning(
            'Warning Title',
            'Warning message'
        );

        $this->assertEquals(Notification::TYPE_WARNING, $notification->type);
        $this->assertEquals('Warning Title', $notification->title);
        $this->assertEquals('Warning message', $notification->message);
    }

    #[Test]
    public function it_creates_info_notification(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->info(
            'Info Title',
            'Info message'
        );

        $this->assertEquals(Notification::TYPE_INFO, $notification->type);
        $this->assertEquals('Info Title', $notification->title);
        $this->assertEquals('Info message', $notification->message);
    }

    #[Test]
    public function it_gets_user_notifications_unread_only(): void
    {
        $user = User::factory()->create();

        // Create notifications
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread 1',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread 2',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
        ]);

        // Expired notification (should not be included)
        $expired = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Expired',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);
        $expired->expires_at = now()->subDay();
        $expired->save();

        $notifications = $this->service->getUserNotifications($user->id, false);

        $this->assertCount(2, $notifications);
        foreach ($notifications as $notification) {
            $this->assertFalse($notification->is_read);
            $this->assertNotEquals('Expired', $notification->title);
        }
    }

    #[Test]
    public function it_gets_user_notifications_including_read(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
        ]);

        $notifications = $this->service->getUserNotifications($user->id, true);

        $this->assertCount(2, $notifications);
    }

    #[Test]
    public function it_gets_notifications_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'User Notification',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $notifications = $this->service->getUserNotifications();

        $this->assertCount(1, $notifications);
        $this->assertEquals($user->id, $notifications->first()->user_id);
    }

    #[Test]
    public function it_gets_unread_count(): void
    {
        $user = User::factory()->create();

        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'info',
                'severity' => 'info',
                'title' => "Unread $i",
                'message' => 'Message',
                'user_id' => $user->id,
                'is_read' => false,
            ]);
        }

        // Create 1 read notification
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
        ]);

        // Create 1 expired unread notification
        $expired = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Expired',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);
        $expired->expires_at = now()->subDay();
        $expired->save();

        $count = $this->service->getUnreadCount($user->id);

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_marks_notification_as_read(): void
    {
        $notification = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'is_read' => false,
        ]);

        $result = $this->service->markAsRead($notification->id);

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    #[Test]
    public function it_returns_false_when_marking_non_existent_notification_as_read(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Notification not found for marking as read', ['id' => 99999]);

        $result = $this->service->markAsRead(99999);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'info',
                'severity' => 'info',
                'title' => "Unread $i",
                'message' => 'Message',
                'user_id' => $user->id,
                'is_read' => false,
            ]);
        }

        $count = $this->service->markAllAsRead($user->id);

        $this->assertEquals(3, $count);

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    #[Test]
    public function it_deletes_notification(): void
    {
        $notification = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
        ]);

        $result = $this->service->delete($notification->id);

        $this->assertTrue($result);
        $this->assertNull(Notification::find($notification->id));
    }

    #[Test]
    public function it_returns_false_when_deleting_non_existent_notification(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Notification not found for deletion', ['id' => 99999]);

        $result = $this->service->delete(99999);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_deletes_read_non_persistent_notifications(): void
    {
        $user = User::factory()->create();

        // Create read non-persistent notifications
        for ($i = 0; $i < 2; $i++) {
            Notification::create([
                'type' => 'info',
                'severity' => 'info',
                'title' => "Read Non-Persistent $i",
                'message' => 'Message',
                'user_id' => $user->id,
                'is_read' => true,
                'is_persistent' => false,
            ]);
        }

        // Create read persistent notification
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read Persistent',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
            'is_persistent' => true,
        ]);

        // Create unread non-persistent notification
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread Non-Persistent',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
            'is_persistent' => false,
        ]);

        $count = $this->service->deleteReadNotifications($user->id);

        $this->assertEquals(2, $count);

        $remainingNotifications = Notification::where('user_id', $user->id)->get();
        $this->assertCount(2, $remainingNotifications);
    }

    #[Test]
    public function it_cleans_up_expired_notifications(): void
    {
        // Create expired notifications
        for ($i = 0; $i < 3; $i++) {
            $notification = new Notification([
                'type' => 'info',
                'severity' => 'info',
                'title' => "Expired $i",
                'message' => 'Message',
                'user_id' => User::factory()->create()->id,
            ]);
            $notification->expires_at = now()->subDay();
            $notification->save();
        }

        // Create active notification
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Active',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'expires_at' => now()->addDay(),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Cleaned up expired notifications', ['count' => 3]);

        $count = $this->service->cleanupExpired();

        $this->assertEquals(3, $count);

        $remainingNotifications = Notification::all();
        $this->assertCount(1, $remainingNotifications);
        $this->assertEquals('Active', $remainingNotifications->first()->title);
    }

    #[Test]
    public function it_does_not_log_when_no_expired_notifications(): void
    {
        // Create only active notifications
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Active',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'expires_at' => now()->addDay(),
        ]);

        Log::shouldReceive('info')->never();

        $count = $this->service->cleanupExpired();

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_creates_ai_provider_notification(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->createAiProviderNotification(
            'error',
            'AI Error',
            'AI service failed',
            ['error_code' => 'AI_001']
        );

        $this->assertEquals('error', $notification->type);
        $this->assertEquals('AI Error', $notification->title);
        $this->assertEquals('AI service failed', $notification->message);
        $this->assertEquals(Notification::SOURCE_AI_PROVIDER, $notification->source);
        $this->assertEquals(['error_code' => 'AI_001'], $notification->data);
    }

    #[Test]
    public function it_creates_notification_with_action(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->createWithAction(
            'info',
            'Action Required',
            'Please review this item',
            'https://example.com/review',
            'Review Now',
            ['item_id' => 123]
        );

        $this->assertEquals('info', $notification->type);
        $this->assertEquals('Action Required', $notification->title);
        $this->assertEquals('Please review this item', $notification->message);
        $this->assertEquals('https://example.com/review', $notification->action_url);
        $this->assertEquals('Review Now', $notification->action_label);
        $this->assertEquals(['item_id' => 123], $notification->data);
    }

    #[Test]
    public function it_broadcasts_notification_to_all_users(): void
    {
        // Create 3 users
        $users = User::factory()->count(3)->create();

        Log::shouldReceive('info')
            ->times(4); // 3 for each notification creation + 1 for broadcast summary

        $count = $this->service->broadcast(
            'info',
            'System Maintenance',
            'The system will be under maintenance',
            ['scheduled_time' => '2024-01-01 00:00:00']
        );

        $this->assertEquals(3, $count);

        // Verify each user received the notification
        foreach ($users as $user) {
            $notifications = Notification::where('user_id', $user->id)->get();
            $this->assertCount(1, $notifications);
            
            $notification = $notifications->first();
            $this->assertEquals('info', $notification->type);
            $this->assertEquals('System Maintenance', $notification->title);
            $this->assertEquals('The system will be under maintenance', $notification->message);
            $this->assertEquals(Notification::SOURCE_SYSTEM, $notification->source);
            $this->assertEquals(['scheduled_time' => '2024-01-01 00:00:00'], $notification->data);
        }
    }

    #[Test]
    public function it_returns_zero_when_broadcasting_to_no_users(): void
    {
        // No users in database
        Log::shouldReceive('info')
            ->once()
            ->with('Broadcast notification sent', \Mockery::type('array'));

        $count = $this->service->broadcast(
            'info',
            'Test Broadcast',
            'Test message'
        );

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_uses_authenticated_user_when_user_id_not_provided(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        Log::shouldReceive('info')->once();

        $notification = $this->service->create([
            'type' => 'info',
            'title' => 'Test',
            'message' => 'Message',
        ]);

        $this->assertEquals($user->id, $notification->user_id);
    }

    #[Test]
    public function it_orders_notifications_by_created_at_desc(): void
    {
        $user = User::factory()->create();

        // Create notifications with different timestamps
        $old = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Old',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $old->created_at = now()->subHours(2);
        $old->save();

        $middle = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Middle',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $middle->created_at = now()->subHour();
        $middle->save();

        $new = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'New',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $new->created_at = now();
        $new->save();

        $notifications = $this->service->getUserNotifications($user->id, true);

        $this->assertCount(3, $notifications);
        $this->assertEquals('New', $notifications[0]->title);
        $this->assertEquals('Middle', $notifications[1]->title);
        $this->assertEquals('Old', $notifications[2]->title);
    }
}