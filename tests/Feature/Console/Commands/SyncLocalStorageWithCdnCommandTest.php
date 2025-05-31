<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncLocalStorageWithCdnCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('bunnycdn');
    }

    #[Test]
    public function it_uploads_missing_files_to_cdn_and_deletes_extra_files_from_cdn()
    {
        Storage::disk('public')->put('uploads/image1.jpg', 'Local Image 1');
        Storage::disk('public')->put('uploads/image2.jpg', 'Local Image 2');

        Storage::disk('bunnycdn')->put('uploads/image2.jpg', 'CDN Image 2');
        Storage::disk('bunnycdn')->put('uploads/image3.jpg', 'CDN Image 3');

        $this->artisan('sync:local-storage-with-cdn')
            ->expectsOutput('Starting synchronization...')
            ->expectsOutput('Synchronization completed successfully.')
            ->assertSuccessful();

        Storage::disk('bunnycdn')->assertExists('uploads/image1.jpg');
        Storage::disk('bunnycdn')->assertExists('uploads/image2.jpg');

        Storage::disk('bunnycdn')->assertMissing('uploads/image3.jpg');
    }

    #[Test]
    public function it_shows_a_message_when_both_storages_are_in_sync()
    {
        Storage::disk('public')->put('uploads/image1.jpg', 'Local Image 1');
        Storage::disk('bunnycdn')->put('uploads/image1.jpg', 'Local Image 1');

        $this->artisan('sync:local-storage-with-cdn')
            ->expectsOutput('Starting synchronization...')
            ->expectsOutput('Nothing to synchronize. Both storages are already in sync.')
            ->assertSuccessful();

        Storage::disk('bunnycdn')->assertExists('uploads/image1.jpg');
    }
}
