<?php

namespace Tests\Browser\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardDebugTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test dashboard access and find console errors
     */
    public function test_dashboard_access_and_console_errors(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->pause(2000)
                ->screenshot('dashboard-main');

            // Get current URL to see where we are
            $currentUrl = $browser->driver->getCurrentURL();
            echo 'Current URL after /dashboard: '.$currentUrl."\n";

            // Check for console errors on main dashboard
            $logs = $browser->driver->manage()->getLog('browser');
            if (! empty($logs)) {
                echo "\n=== Console logs on /dashboard ===\n";
                foreach ($logs as $log) {
                    echo sprintf("[%s] %s\n", $log['level'], $log['message']);
                }
            }

            // Try to visit api-logs page
            $browser->visit('/dashboard/api-logs')
                ->pause(2000)
                ->screenshot('dashboard-api-logs');

            // Get current URL
            $currentUrl = $browser->driver->getCurrentURL();
            echo "\nCurrent URL after /dashboard/api-logs: ".$currentUrl."\n";

            // Get page source to see what's rendered
            $pageSource = $browser->driver->getPageSource();
            if (strpos($pageSource, 'error') !== false || strpos($pageSource, 'Error') !== false) {
                echo "\n=== Page contains error text ===\n";
                // Extract just the body content if possible
                if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $pageSource, $matches)) {
                    $bodyContent = strip_tags($matches[1]);
                    echo substr($bodyContent, 0, 1000)."\n";
                }
            }

            // Check for console errors on api-logs page
            $logs = $browser->driver->manage()->getLog('browser');
            if (! empty($logs)) {
                echo "\n=== Console logs on /dashboard/api-logs ===\n";
                foreach ($logs as $log) {
                    if ($log['level'] === 'SEVERE') {
                        echo sprintf("[ERROR] %s\n", $log['message']);
                    } else {
                        echo sprintf("[%s] %s\n", $log['level'], $log['message']);
                    }
                }
            }

            // Try to find specific elements
            $elements = [
                'notification-bell' => '[data-testid="notification-bell"]',
                'api-logs-table' => '[data-testid="api-logs-table"]',
                'h1' => 'h1',
                'table' => 'table',
                'error-message' => '.error-message',
                'alert' => '.alert',
            ];

            echo "\n=== Element presence check ===\n";
            foreach ($elements as $name => $selector) {
                try {
                    if ($browser->element($selector)) {
                        echo "✓ Found: $name ($selector)\n";
                        if ($name === 'h1' || $name === 'error-message' || $name === 'alert') {
                            $text = $browser->text($selector);
                            echo "  Text: $text\n";
                        }
                    }
                } catch (\Exception $e) {
                    echo "✗ Not found: $name ($selector)\n";
                }
            }
        });
    }

    /**
     * Test routes availability
     */
    public function test_routes_availability(): void
    {
        $user = User::factory()->create();

        $routes = [
            '/dashboard' => 'Dashboard',
            '/dashboard/api-logs' => 'API Logs',
            '/dashboard/notifications' => 'Notifications',
        ];

        foreach ($routes as $route => $name) {
            $this->browse(function (Browser $browser) use ($user, $route, $name) {
                echo "\n=== Testing route: $route ($name) ===\n";

                $browser->loginAs($user)
                    ->visit($route)
                    ->pause(1000);

                // Get HTTP response status
                $currentUrl = $browser->driver->getCurrentURL();
                echo "URL: $currentUrl\n";

                // Check page title
                $title = $browser->driver->getTitle();
                echo "Page title: $title\n";

                // Look for common error indicators
                $pageSource = $browser->driver->getPageSource();
                if (strpos($pageSource, '404') !== false) {
                    echo "⚠️ Page contains '404'\n";
                }
                if (strpos($pageSource, '500') !== false) {
                    echo "⚠️ Page contains '500'\n";
                }
                if (strpos($pageSource, 'Exception') !== false) {
                    echo "⚠️ Page contains 'Exception'\n";
                }

                $browser->screenshot('route-'.str_replace('/', '-', $route));
            });
        }
    }
}
