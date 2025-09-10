<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService;
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_create_notification(): void
    {
        $notification = $this->service->create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SUCCESS,
            'title' => 'Test Notification',
            'message' => 'This is a test message',
            'data' => ['test' => 'data'],
        ]);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals('Test Notification', $notification->title);
        $this->assertEquals('This is a test message', $notification->message);
        $this->assertEquals(Notification::TYPE_SUCCESS, $notification->type);
        $this->assertEquals(['test' => 'data'], $notification->data);
    }

    public function test_can_create_success_notification(): void
    {
        $notification = $this->service->success('Success', 'Operation completed successfully');

        $this->assertEquals(Notification::TYPE_SUCCESS, $notification->type);
        $this->assertEquals('Success', $notification->title);
        $this->assertEquals('Operation completed successfully', $notification->message);
    }

    public function test_can_create_error_notification(): void
    {
        $notification = $this->service->error('Error', 'An error occurred');

        $this->assertEquals(Notification::TYPE_ERROR, $notification->type);
        $this->assertEquals('Error', $notification->title);
        $this->assertEquals('An error occurred', $notification->message);
    }

    public function test_can_get_user_notifications(): void
    {
        // Create some notifications
        $this->service->success('Success 1', 'Message 1');
        $this->service->error('Error 1', 'Message 2');
        $this->service->info('Info 1', 'Message 3');

        $notifications = $this->service->getUserNotifications($this->user->id);

        $this->assertCount(3, $notifications);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = $this->service->success('Success', 'Message');

        $this->assertFalse((bool) $notification->is_read);
        $this->assertNull($notification->read_at);

        $result = $this->service->markAsRead($notification->id);

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertTrue((bool) $notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_can_create_ai_provider_notification(): void
    {
        $notification = $this->service->createAiProviderNotification(
            Notification::TYPE_ERROR,
            'AI Provider Error',
            'Failed to connect to API',
            ['provider' => 'openai']
        );

        $this->assertEquals(Notification::SOURCE_AI_PROVIDER, $notification->source);
        $this->assertEquals('AI Provider Error', $notification->title);
        $this->assertEquals(['provider' => 'openai'], $notification->data);
    }
}
