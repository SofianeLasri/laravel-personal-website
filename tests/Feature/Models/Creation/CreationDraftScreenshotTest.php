<?php

namespace Models\Creation;

use App\Models\CreationDraft as Creation;
use App\Models\CreationDraftScreenshot as Screenshot;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Screenshot::class)]
class CreationDraftScreenshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_screenshot()
    {
        $creation = Creation::factory()->create();
        $picture = Picture::factory()->create();

        $screenshot = Screenshot::factory()->create([
            'creation_draft_id' => $creation->id,
            'picture_id' => $picture->id,
        ]);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot->id,
            'creation_draft_id' => $creation->id,
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_creation()
    {
        $creation = Creation::factory()->create();
        $screenshot = Screenshot::factory()->create(['creation_draft_id' => $creation->id]);

        $this->assertInstanceOf(Creation::class, $screenshot->creationDraft);
        $this->assertEquals($creation->id, $screenshot->creationDraft->id);
    }

    #[Test]
    public function it_has_a_picture()
    {
        $picture = Picture::factory()->create();
        $screenshot = Screenshot::factory()->create(['picture_id' => $picture->id]);

        $this->assertInstanceOf(Picture::class, $screenshot->picture);
        $this->assertEquals($picture->id, $screenshot->picture->id);
    }

    #[Test]
    public function it_can_have_an_optional_caption()
    {
        $captionKey = TranslationKey::factory()->create(['key' => 'screenshot.caption.test']);

        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'fr',
            'text' => 'Légende en français',
        ]);

        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'Caption in English',
        ]);

        $screenshot = Screenshot::factory()->create([
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $this->assertEquals('Légende en français', $screenshot->getCaption('fr'));
        $this->assertEquals('Caption in English', $screenshot->getCaption('en'));

        $screenshotWithoutCaption = Screenshot::factory()->create([
            'caption_translation_key_id' => null,
        ]);

        $this->assertEquals('', $screenshotWithoutCaption->getCaption('fr'));
    }
}
