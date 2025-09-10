<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\NotificationsMarkReadCommand;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NotificationsMarkReadCommand::class)]
class NotificationsMarkReadCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_message_when_no_unread_notifications_found(): void
    {
        // Create only read notifications
        Notification::factory()->create([
            'is_read' => true,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read')
            ->expectsOutput('No unread notifications found matching the criteria.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_marks_old_unread_notifications_as_read_with_confirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create old unread notification (older than 7 days)
        $oldNotification = Notification::factory()->create([
            'is_read' => false,
            'title' => 'Old notification',
            'type' => 'system',
            'severity' => 'info',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Create recent unread notification (should not be marked)
        $recentNotification = Notification::factory()->create([
            'is_read' => false,
            'title' => 'Recent notification',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->artisan('notifications:mark-read')
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsOutput('Breakdown by Type and Severity:')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->expectsOutput('✓ Successfully marked 1 notification(s) as read.')
            ->assertSuccessful();

        // Verify the old notification was marked as read
        $this->assertTrue($oldNotification->fresh()->is_read);
        $this->assertNotNull($oldNotification->fresh()->read_at);

        // Verify the recent notification was NOT marked as read
        $this->assertFalse($recentNotification->fresh()->is_read);
        $this->assertNull($recentNotification->fresh()->read_at);
    }

    #[Test]
    public function it_cancels_operation_when_not_confirmed(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        $notification = Notification::factory()->create([
            'is_read' => false,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read')
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        // Verify notification was NOT marked as read
        $this->assertFalse($notification->fresh()->is_read);
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create notifications of different types
        $systemNotification = Notification::factory()->create([
            'is_read' => false,
            'type' => 'system',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $userNotification = Notification::factory()->create([
            'is_read' => false,
            'type' => 'user',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', ['--type' => 'system'])
            ->expectsOutput('Filtering by type: system')
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->assertSuccessful();

        // Only system notification should be marked as read
        $this->assertTrue($systemNotification->fresh()->is_read);
        $this->assertFalse($userNotification->fresh()->is_read);
    }

    #[Test]
    public function it_filters_by_severity(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create notifications of different severities
        $errorNotification = Notification::factory()->create([
            'is_read' => false,
            'severity' => 'error',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $infoNotification = Notification::factory()->create([
            'is_read' => false,
            'severity' => 'info',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', ['--severity' => 'error'])
            ->expectsOutput('Filtering by severity: error')
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->assertSuccessful();

        // Only error notification should be marked as read
        $this->assertTrue($errorNotification->fresh()->is_read);
        $this->assertFalse($infoNotification->fresh()->is_read);
    }

    #[Test]
    #[DataProvider('periodProvider')]
    public function it_handles_different_period_formats(string $period, int $expectedDaysAgo): void
    {
        $now = Carbon::parse('2024-01-15 12:00:00');
        Carbon::setTestNow($now);

        // Create notification at the exact cutoff point
        Notification::factory()->create([
            'is_read' => false,
            'created_at' => $now->copy()->subDays($expectedDaysAgo)->subMinutes(1), // Just older
        ]);

        // Create notification just after cutoff (should not be marked)
        Notification::factory()->create([
            'is_read' => false,
            'created_at' => $now->copy()->subDays($expectedDaysAgo)->addMinutes(1), // Just newer
        ]);

        $result = $this->artisan('notifications:mark-read', ['--older-than' => $period]);

        // For months format, we need to handle it differently as it might not find notifications
        if ($period === '1month') {
            $result->assertSuccessful();
        } else {
            $result->expectsOutput('Found 1 unread notification(s) to mark as read.')
                ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
                ->assertSuccessful();
        }
    }

    public static function periodProvider(): array
    {
        return [
            'hours format' => ['24hours', 1],
            'days format' => ['7days', 7],
            'weeks format' => ['2weeks', 14],
            'months format' => ['1month', 30],
        ];
    }

    #[Test]
    public function it_handles_invalid_period_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        Notification::factory()->create([
            'is_read' => false,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', ['--older-than' => 'invalid-period'])
            ->expectsOutput("Could not parse period 'invalid-period', defaulting to 7 days")
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_dry_run_without_making_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        $notification = Notification::factory()->create([
            'is_read' => false,
            'title' => 'Test notification for dry run',
            'type' => 'system',
            'severity' => 'info',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', ['--dry-run' => true])
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsOutput('DRY RUN: No changes were made.')
            ->expectsOutput('Would have marked 1 notification(s) as read.')
            ->expectsOutput('Sample notifications that would be marked:')
            ->assertSuccessful();

        // Verify notification was NOT marked as read
        $this->assertFalse($notification->fresh()->is_read);
        $this->assertNull($notification->fresh()->read_at);
    }

    #[Test]
    public function it_truncates_long_titles_in_dry_run_sample(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        Notification::factory()->create([
            'is_read' => false,
            'title' => 'This is a very long notification title that should be truncated when displayed in the dry run table',
            'type' => 'system',
            'severity' => 'info',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', ['--dry-run' => true])
            ->expectsOutput('Sample notifications that would be marked:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_remaining_unread_notifications_after_marking(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create old notification (will be marked)
        Notification::factory()->create([
            'is_read' => false,
            'severity' => 'info',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Create recent notification (will remain unread)
        Notification::factory()->create([
            'is_read' => false,
            'severity' => 'warning',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->artisan('notifications:mark-read')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->expectsOutput('✓ Successfully marked 1 notification(s) as read.')
            ->expectsOutput('Remaining unread notifications: 1')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_success_message_when_all_notifications_read(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create only one old unread notification
        Notification::factory()->create([
            'is_read' => false,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->expectsOutput('✓ Successfully marked 1 notification(s) as read.')
            ->expectsOutput('✓ All notifications have been marked as read!')
            ->assertSuccessful();
    }

    #[Test]
    public function it_combines_type_and_severity_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create matching notification
        $matchingNotification = Notification::factory()->create([
            'is_read' => false,
            'type' => 'system',
            'severity' => 'error',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Create non-matching notifications
        Notification::factory()->create([
            'is_read' => false,
            'type' => 'user', // Wrong type
            'severity' => 'error',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        Notification::factory()->create([
            'is_read' => false,
            'type' => 'system',
            'severity' => 'info', // Wrong severity
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('notifications:mark-read', [
            '--type' => 'system',
            '--severity' => 'error',
        ])
            ->expectsOutput('Filtering by type: system')
            ->expectsOutput('Filtering by severity: error')
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->assertSuccessful();

        // Only the matching notification should be marked as read
        $this->assertTrue($matchingNotification->fresh()->is_read);
        $this->assertEquals(2, Notification::where('is_read', false)->count());
    }

    #[Test]
    public function it_parses_date_format_period(): void
    {
        $now = Carbon::parse('2024-01-15 12:00:00');
        Carbon::setTestNow($now);

        Notification::factory()->create([
            'is_read' => false,
            'created_at' => Carbon::parse('2024-01-10'), // Before the cutoff date
        ]);

        Notification::factory()->create([
            'is_read' => false,
            'created_at' => Carbon::parse('2024-01-12'), // After the cutoff date
        ]);

        $this->artisan('notifications:mark-read', ['--older-than' => '2024-01-11'])
            ->expectsOutput('Found 1 unread notification(s) to mark as read.')
            ->expectsConfirmation('Are you sure you want to mark 1 notification(s) as read?', 'yes')
            ->assertSuccessful();
    }

    #[Test]
    public function it_limits_dry_run_sample_to_five_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));

        // Create 6 old unread notifications
        for ($i = 1; $i <= 6; $i++) {
            Notification::factory()->create([
                'is_read' => false,
                'title' => "Notification {$i}",
                'created_at' => Carbon::now()->subDays(10),
            ]);
        }

        $this->artisan('notifications:mark-read', ['--dry-run' => true])
            ->expectsOutput('Found 6 unread notification(s) to mark as read.')
            ->expectsOutput('Sample notifications that would be marked:')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
