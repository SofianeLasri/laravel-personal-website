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
                // Wait for the notification count badge to become visible and show "2"
                ->waitUntilMissing('[data-testid="notification-count"].sr-only', 10)
                ->waitFor('[data-testid="notification-count"]:not(.sr-only)', 10)
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
                // Wait for notifications to load before opening popup
                ->waitUntilMissing('[data-testid="notification-count"].sr-only', 10)
                ->click('[data-testid="notification-bell"]')
                ->waitFor('[data-testid="notification-popup"]', 5)
                // Wait for notifications to be rendered in the popup
                ->waitForText('Error Notification 0', 5)
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

}
