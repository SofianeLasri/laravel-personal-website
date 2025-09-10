<?php

namespace Tests\Browser\Dashboard;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationDebugTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Debug notification UI elements
     */
    public function test_debug_notification_elements(): void
    {
        $user = User::factory()->create();

        // Create a test notification
        Notification::create([
            'type' => 'ai_provider_error',
            'severity' => 'error',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'is_read' => false,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->pause(2000)
                ->screenshot('dashboard-with-notification');

            // Look for various possible notification elements
            $selectors = [
                '[data-testid="notification-bell"]' => 'notification-bell',
                '.notification-bell' => 'class notification-bell',
                '#notification-bell' => 'id notification-bell',
                '[aria-label*="notification"]' => 'aria-label notification',
                'button[title*="notification"]' => 'button with notification title',
                'svg[class*="bell"]' => 'svg with bell class',
                '.bell-icon' => 'bell-icon class',
                '[class*="notification"]' => 'any element with notification class',
            ];

            echo "\n=== Searching for notification elements ===\n";
            foreach ($selectors as $selector => $description) {
                try {
                    $elements = $browser->elements($selector);
                    if (! empty($elements)) {
                        echo "✓ Found {$description}: ".count($elements)." element(s)\n";
                        // Try to get text or attributes
                        foreach ($elements as $index => $element) {
                            try {
                                $text = $element->getText();
                                if ($text) {
                                    echo "  Element $index text: $text\n";
                                }
                            } catch (\Exception $e) {
                                // Element might not have text
                            }
                        }
                    } else {
                        echo "✗ Not found: {$description}\n";
                    }
                } catch (\Exception $e) {
                    echo "✗ Error checking {$description}: ".$e->getMessage()."\n";
                }
            }

            // Check page source for notification-related content
            $pageSource = $browser->driver->getPageSource();
            if (strpos($pageSource, 'notification') !== false) {
                echo "\n✓ Page contains 'notification' text\n";
            }
            if (strpos($pageSource, 'bell') !== false) {
                echo "✓ Page contains 'bell' text\n";
            }

            // Try to find notification count
            $countSelectors = [
                '[data-testid="notification-count"]',
                '.notification-count',
                '.badge',
                '[class*="count"]',
            ];

            echo "\n=== Searching for notification count ===\n";
            foreach ($countSelectors as $selector) {
                try {
                    $elements = $browser->elements($selector);
                    if (! empty($elements)) {
                        echo "✓ Found count element: $selector\n";
                        foreach ($elements as $element) {
                            try {
                                $text = $element->getText();
                                if ($text) {
                                    echo "  Count text: $text\n";
                                }
                            } catch (\Exception $e) {
                                // Ignore
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        });
    }
}
