<?php

namespace Tests\Browser\Dashboard;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationsTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test notification popup appears
     */
    public function test_notification_popup_appears(): void
    {
        $user = User::factory()->create();

        // Create unread notifications
        Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'AI Provider Failed',
            'message' => 'OpenAI API returned an error',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        Notification::create([
            'type' => 'ai_provider_fallback',
            'severity' => 'warning',
            'title' => 'Fallback Provider Used',
            'message' => 'Switched from OpenAI to Anthropic',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->assertPresent('[data-testid="notification-bell"]')
                ->pause(2000) // Wait for notifications to load
                ->assertSeeIn('[data-testid="notification-count"]', '2')
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                ->assertPresent('[data-testid="notification-popup"]')
                ->assertSee('AI Provider Failed')
                ->assertSee('Fallback Provider Used')
                ->screenshot('notification-popup');
        });
    }

    /**
     * Test marking notification as read
     */
    public function test_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $notification) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->assertSeeIn('[data-testid="notification-count"]', '1')
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                ->click("[data-testid='mark-read-{$notification->id}']")
                ->pause(500)
                ->assertSeeIn('[data-testid="notification-count"]', '0')
                ->screenshot('notification-marked-read');
        });

        // Verify in database
        $this->assertTrue(
            Notification::find($notification->id)->is_read,
            'Notification should be marked as read in database'
        );
    }

    /**
     * Test notification severity indicators
     */
    public function test_notification_severity_indicators(): void
    {
        $user = User::factory()->create();

        // Create notifications with different severities
        Notification::create([
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Info Notification',
            'message' => 'This is an info message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        Notification::create([
            'type' => 'ai_provider',
            'severity' => 'warning',
            'title' => 'Warning Notification',
            'message' => 'This is a warning message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        Notification::create([
            'type' => 'ai_provider',
            'severity' => 'error',
            'title' => 'Error Notification',
            'message' => 'This is an error message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        Notification::create([
            'type' => 'system',
            'severity' => 'critical',
            'title' => 'Critical Notification',
            'message' => 'This is a critical message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                ->assertPresent('[data-testid="severity-info"]')
                ->assertPresent('[data-testid="severity-warning"]')
                ->assertPresent('[data-testid="severity-error"]')
                ->assertPresent('[data-testid="severity-critical"]')
                ->screenshot('notification-severities');
        });
    }

    /**
     * Test notification filtering
     */
    public function test_notification_filtering(): void
    {
        $user = User::factory()->create();

        // Create various notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'type' => 'ai_provider_error',
                'severity' => 'error',
                'title' => "Error Notification $i",
                'message' => "Error message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Notification::create([
                'type' => 'system',
                'severity' => 'info',
                'title' => "Info Notification $i",
                'message' => "Info message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                // Filter by severity
                ->select('[data-testid="severity-filter"]', 'error')
                ->pause(500)
                ->assertSee('Error Notification')
                ->assertDontSee('Info Notification')
                // Clear filter
                ->select('[data-testid="severity-filter"]', '')
                ->pause(500)
                ->assertSee('Error Notification')
                ->assertSee('Info Notification')
                ->screenshot('notification-filtering');
        });
    }

    /**
     * Test notification auto-refresh
     */
    public function test_notification_auto_refresh(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->assertSeeIn('[data-testid="notification-count"]', '0');

            // Create a new notification while the page is open
            Notification::create([
                'type' => 'ai_provider_error',
                'severity' => 'error',
                'title' => 'New Error',
                'message' => 'A new error occurred',
                'is_read' => false,
                'user_id' => $user->id,
            ]);

            // Wait for auto-refresh (assuming it's set to refresh every few seconds)
            $browser->pause(5000)
                ->assertSeeIn('[data-testid="notification-count"]', '1')
                ->screenshot('notification-auto-refresh');
        });
    }

    /**
     * Test mark all as read
     */
    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        // Create multiple notifications
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'type' => 'ai_provider_error',
                'severity' => 'error',
                'title' => "Notification $i",
                'message' => "Message $i",
                'is_read' => false,
                'user_id' => $user->id,
            ]);
        }

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->assertSeeIn('[data-testid="notification-count"]', '5')
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                ->click('[data-testid="mark-all-read"]')
                ->pause(1000)
                ->assertSeeIn('[data-testid="notification-count"]', '0')
                ->screenshot('mark-all-read');
        });

        // Verify all are marked as read in database
        $this->assertEquals(
            0,
            Notification::where('user_id', $user->id)->where('is_read', false)->count(),
            'All notifications should be marked as read'
        );
    }

    /**
     * Test notification details modal
     */
    public function test_notification_details_modal(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'Detailed Error',
            'message' => 'This is a detailed error message with lots of information',
            'context' => [
                'provider' => 'openai',
                'error_code' => 500,
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ],
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $notification) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                ->click("[data-testid='view-details-{$notification->id}']")
                ->waitFor('[data-testid="notification-modal"]', 5)
                ->assertSee('Detailed Error')
                ->assertSee('This is a detailed error message')
                ->assertSee('openai')
                ->assertSee('500')
                ->assertSee('https://api.openai.com')
                ->screenshot('notification-details-modal');
        });
    }

    /**
     * Test no console errors for notifications
     */
    public function test_no_console_errors_for_notifications(): void
    {
        $user = User::factory()->create();

        // Create some notifications
        Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'Test Error',
            'message' => 'Test error message',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('[data-testid="notification-bell"]', 10)
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5);

            // Check for console errors
            $logs = $browser->driver->manage()->getLog('browser');
            $errors = array_filter($logs, function ($log) {
                return $log['level'] === 'SEVERE';
            });

            if (! empty($errors)) {
                $browser->screenshot('notification-console-errors');
                $this->fail('Console errors found: '.json_encode($errors, JSON_PRETTY_PRINT));
            }

            $this->assertEmpty($errors, 'No console errors should be present');
        });
    }
}
