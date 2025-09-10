<?php

namespace Tests\Feature\Models\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Notification::class)]
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test Notification',
            'message' => 'Test message',
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $notification->user);
        $this->assertEquals($user->id, $notification->user->id);
    }

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'type' => 'success',
            'severity' => 'success',
            'title' => 'Success Notification',
            'message' => 'Operation completed successfully',
            'data' => ['key' => 'value', 'nested' => ['data' => 'here']],
            'context' => ['request_id' => '123'],
            'source' => 'system',
            'action_url' => 'https://example.com/action',
            'action_label' => 'View Details',
            'is_read' => false,
            'is_persistent' => true,
            'read_at' => null,
            'expires_at' => now()->addDays(7),
        ];

        $notification = Notification::create($data);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'success',
            'severity' => 'success',
            'title' => 'Success Notification',
            'message' => 'Operation completed successfully',
            'source' => 'system',
            'action_url' => 'https://example.com/action',
            'action_label' => 'View Details',
            'is_read' => false,
            'is_persistent' => true,
        ]);

        $this->assertEquals($data['data'], $notification->data);
        $this->assertEquals($data['context'], $notification->context);
    }

    #[Test]
    public function it_scopes_to_unread_notifications(): void
    {
        $user = User::factory()->create();

        // Create 2 unread and 1 read notification
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
            'read_at' => now(),
        ]);

        $unreadNotifications = Notification::unread()->get();

        $this->assertCount(2, $unreadNotifications);
        foreach ($unreadNotifications as $notification) {
            $this->assertFalse($notification->is_read);
        }
    }

    #[Test]
    public function it_scopes_to_read_notifications(): void
    {
        $user = User::factory()->create();

        // Create 1 unread and 2 read notifications
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
            'title' => 'Read 1',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read 2',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $readNotifications = Notification::read()->get();

        $this->assertCount(2, $readNotifications);
        foreach ($readNotifications as $notification) {
            $this->assertTrue($notification->is_read);
        }
    }

    #[Test]
    public function it_scopes_to_specific_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create notifications for different users
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'User 1 Notification 1',
            'message' => 'Message',
            'user_id' => $user1->id,
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'User 1 Notification 2',
            'message' => 'Message',
            'user_id' => $user1->id,
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'User 2 Notification',
            'message' => 'Message',
            'user_id' => $user2->id,
        ]);

        $user1Notifications = Notification::forUser($user1->id)->get();
        $user2Notifications = Notification::forUser($user2->id)->get();

        $this->assertCount(2, $user1Notifications);
        $this->assertCount(1, $user2Notifications);

        foreach ($user1Notifications as $notification) {
            $this->assertEquals($user1->id, $notification->user_id);
        }
    }

    #[Test]
    public function it_scopes_to_non_expired_notifications(): void
    {
        $user = User::factory()->create();

        // Create expired notification
        $expired = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Expired',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $expired->expires_at = now()->subDay();
        $expired->save();

        // Create active notification
        $active = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Active',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $active->expires_at = now()->addDay();
        $active->save();

        // Create notification without expiry
        $noExpiry = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'No Expiry',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $noExpiry->expires_at = null;
        $noExpiry->save();

        $notExpired = Notification::notExpired()->get();

        $this->assertCount(2, $notExpired);
        $titles = $notExpired->pluck('title')->toArray();
        $this->assertContains('Active', $titles);
        $this->assertContains('No Expiry', $titles);
        $this->assertNotContains('Expired', $titles);
    }

    #[Test]
    public function it_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'is_read' => false,
        ]);

        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);

        $result = $notification->markAsRead();

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
        $this->assertTrue($notification->read_at->isToday());
    }

    #[Test]
    public function it_does_not_update_read_at_when_already_read(): void
    {
        $readAt = now()->subDays(3);
        $notification = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'is_read' => true,
        ]);
        $notification->read_at = $readAt;
        $notification->save();

        $originalReadAt = $notification->read_at->format('Y-m-d H:i:s');

        $result = $notification->markAsRead();

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertEquals($originalReadAt, $notification->read_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_can_mark_notification_as_unread(): void
    {
        $notification = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => User::factory()->create()->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);

        $result = $notification->markAsUnread();

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);
    }

    #[Test]
    public function it_can_check_if_notification_has_expired(): void
    {
        $user = User::factory()->create();

        // Expired notification
        $expired = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Expired',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $expired->expires_at = now()->subDay();
        $expired->save();

        // Active notification
        $active = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Active',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $active->expires_at = now()->addDay();
        $active->save();

        // No expiry notification
        $noExpiry = Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'No Expiry',
            'message' => 'Message',
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $this->assertTrue($expired->hasExpired());
        $this->assertFalse($active->hasExpired());
        $this->assertFalse($noExpiry->hasExpired());
    }

    #[Test]
    public function it_returns_correct_icon_based_on_type(): void
    {
        $user = User::factory()->create();

        $types = [
            'success' => 'check-circle',
            'error' => 'x-circle',
            'warning' => 'alert-triangle',
            'info' => 'info',
        ];

        foreach ($types as $type => $expectedIcon) {
            $notification = Notification::create([
                'type' => $type,
                'severity' => $type,
                'title' => "Test $type",
                'message' => 'Message',
                'user_id' => $user->id,
            ]);

            $this->assertEquals($expectedIcon, $notification->getIcon());
        }

        // Test unknown type
        $notification = new Notification([
            'type' => 'unknown',
            'severity' => 'unknown',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $this->assertEquals('bell', $notification->getIcon());
    }

    #[Test]
    public function it_returns_correct_color_class_based_on_type(): void
    {
        $user = User::factory()->create();

        $types = [
            'success' => 'text-green-500',
            'error' => 'text-red-500',
            'warning' => 'text-yellow-500',
            'info' => 'text-blue-500',
        ];

        foreach ($types as $type => $expectedClass) {
            $notification = Notification::create([
                'type' => $type,
                'severity' => $type,
                'title' => "Test $type",
                'message' => 'Message',
                'user_id' => $user->id,
            ]);

            $this->assertEquals($expectedClass, $notification->getColorClass());
        }

        // Test unknown type
        $notification = new Notification([
            'type' => 'unknown',
            'severity' => 'unknown',
            'title' => 'Test',
            'message' => 'Message',
            'user_id' => $user->id,
        ]);
        $this->assertEquals('text-gray-500', $notification->getColorClass());
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $user = User::factory()->create();
        $now = now();
        $expiresAt = now()->addDays(7);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'data' => ['key' => 'value'],
            'context' => ['request' => '123'],
            'is_read' => true,
            'is_persistent' => true,
            'read_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        // Test array casting
        $this->assertIsArray($notification->data);
        $this->assertEquals(['key' => 'value'], $notification->data);
        $this->assertIsArray($notification->context);
        $this->assertEquals(['request' => '123'], $notification->context);

        // Test boolean casting
        $this->assertIsBool($notification->is_read);
        $this->assertTrue($notification->is_read);
        $this->assertIsBool($notification->is_persistent);
        $this->assertTrue($notification->is_persistent);

        // Test datetime casting
        $this->assertInstanceOf(Carbon::class, $notification->read_at);
        $this->assertInstanceOf(Carbon::class, $notification->expires_at);
    }

    #[Test]
    public function it_handles_null_data_and_context(): void
    {
        $notification = Notification::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
            'data' => null,
            'context' => null,
        ]);

        $this->assertNull($notification->data);
        $this->assertNull($notification->context);
    }

    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $fillable = [
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

        $notification = new Notification;
        $this->assertEquals($fillable, $notification->getFillable());
    }

    #[Test]
    public function it_has_correct_type_constants(): void
    {
        $this->assertEquals('success', Notification::TYPE_SUCCESS);
        $this->assertEquals('error', Notification::TYPE_ERROR);
        $this->assertEquals('warning', Notification::TYPE_WARNING);
        $this->assertEquals('info', Notification::TYPE_INFO);
    }

    #[Test]
    public function it_has_correct_source_constants(): void
    {
        $this->assertEquals('ai_provider', Notification::SOURCE_AI_PROVIDER);
        $this->assertEquals('system', Notification::SOURCE_SYSTEM);
        $this->assertEquals('user', Notification::SOURCE_USER);
    }

    #[Test]
    public function it_can_chain_multiple_scopes(): void
    {
        $user = User::factory()->create();

        // Create various notifications
        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread Active',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
            'expires_at' => now()->addDay(),
        ]);

        Notification::create([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Read Active',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $expired = new Notification([
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Unread Expired',
            'message' => 'Message',
            'user_id' => $user->id,
            'is_read' => false,
        ]);
        $expired->expires_at = now()->subDay();
        $expired->save();

        // Test chaining scopes
        $unreadActiveNotifications = Notification::forUser($user->id)
            ->unread()
            ->notExpired()
            ->get();

        $this->assertCount(1, $unreadActiveNotifications);
        $this->assertEquals('Unread Active', $unreadActiveNotifications->first()->title);
    }

    #[Test]
    public function it_handles_mass_assignment_protection(): void
    {
        $notification = new Notification;

        // These should be fillable
        $notification->fill([
            'type' => 'info',
            'title' => 'Test',
            'message' => 'Message',
        ]);

        $this->assertEquals('info', $notification->type);
        $this->assertEquals('Test', $notification->title);
        $this->assertEquals('Message', $notification->message);

        // ID should not be fillable (protected by Laravel)
        $notification->fill(['id' => 999]);
        $this->assertNull($notification->id);
    }

    #[Test]
    public function it_can_be_soft_deleted_if_configured(): void
    {
        // Test that the model doesn't use soft deletes by default
        $notification = Notification::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'info',
            'severity' => 'info',
            'title' => 'Test',
            'message' => 'Message',
        ]);

        $id = $notification->id;
        $notification->delete();

        $this->assertNull(Notification::find($id));
        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }
}
