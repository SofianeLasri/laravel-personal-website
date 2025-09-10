<?php

namespace Tests\Browser\Dashboard;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationApiTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test API returns notifications correctly
     */
    public function test_api_returns_notifications(): void
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

        $this->browse(function (Browser $browser) use ($user, $notification1, $notification2) {
            $browser->loginAs($user);

            // Make API request via JavaScript console
            $response = $browser->script("
                return fetch('/dashboard/api/notifications', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    return data;
                })
                .catch(error => {
                    console.error('API Error:', error);
                    return { error: error.message };
                });
            ");

            // Debug output
            dump('API Response:', $response[0]);

            // Check response structure
            if (isset($response[0]['data'])) {
                $data = $response[0]['data'];

                // Check if it's paginated or not
                if (isset($data['data'])) {
                    // Paginated response
                    $notifications = $data['data'];
                    echo 'Paginated response with '.count($notifications)." notifications\n";
                } elseif (is_array($data)) {
                    // Non-paginated response
                    $notifications = $data;
                    echo 'Non-paginated response with '.count($notifications)." notifications\n";
                } else {
                    echo "Unexpected data structure\n";
                    dump($data);
                }

                // Verify we have the right notifications
                $this->assertCount(2, $notifications, 'Should have 2 notifications for this user');

                // Check notification IDs
                $ids = array_map(fn ($n) => $n['id'], $notifications);
                $this->assertContains($notification1->id, $ids);
                $this->assertContains($notification2->id, $ids);
            } else {
                $this->fail('No data field in API response');
            }
        });
    }

    /**
     * Test unread count API
     */
    public function test_unread_count_api(): void
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

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            // Make API request for unread count
            $response = $browser->script("
                return fetch('/dashboard/api/notifications/unread-count', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Unread Count Response:', data);
                    return data;
                })
                .catch(error => {
                    console.error('API Error:', error);
                    return { error: error.message };
                });
            ");

            dump('Unread Count Response:', $response[0]);

            $this->assertEquals(3, $response[0]['count'], 'Should have 3 unread notifications');
        });
    }
}
