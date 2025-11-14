<?php

declare(strict_types=1);

namespace Tests\Browser\Dashboard;

use App\Models\ContentMarkdown;
use App\Models\CreationDraft;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

class MarkdownEditorRawModeTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_allows_scrolling_in_raw_markdown_mode(): void
    {
        // Create a long markdown content
        $longContent = implode("\n\n", array_map(
            fn ($i) => "## Section $i\n\nThis is paragraph $i with some content to make it longer. ".str_repeat("Lorem ipsum dolor sit amet. ", 10),
            range(1, 20)
        ));

        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => $longContent,
            'fr' => $longContent,
        ])->create();

        $draft = CreationDraft::factory()->create([
            'name' => 'Test Draft for Raw Mode',
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($draft) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?draft-id='.$draft->id)
                ->waitForText('Markdown')
                ->pause(2000); // Wait for content to load

            // Toggle to raw markdown mode (click the icon button)
            $browser->click('[data-testid="toggle-raw-mode"]')
                ->pause(500) // Wait for mode switch
                ->assertVisible('.raw-markdown-editor');

            // Verify the textarea has overflow-y property set (via computed styles)
            $overflowY = $browser->script(
                'return window.getComputedStyle(document.querySelector(".raw-markdown-editor")).overflowY;'
            )[0];

            $this->assertEquals('auto', $overflowY, 'Raw markdown editor should have overflow-y: auto for scrolling');

            // Verify scrollHeight is greater than clientHeight (content is scrollable)
            $isScrollable = $browser->script(
                'const el = document.querySelector(".raw-markdown-editor"); return el.scrollHeight > el.clientHeight;'
            )[0];

            $this->assertTrue($isScrollable, 'Raw markdown editor should be scrollable when content is long');

            // Verify we can scroll (scroll to bottom and check scrollTop changed)
            $browser->script(
                'document.querySelector(".raw-markdown-editor").scrollTop = document.querySelector(".raw-markdown-editor").scrollHeight;'
            );

            $scrollTop = $browser->script(
                'return document.querySelector(".raw-markdown-editor").scrollTop;'
            )[0];

            $this->assertGreaterThan(0, $scrollTop, 'Should be able to scroll in raw markdown editor');
        });
    }

    #[Test]
    public function it_has_proper_height_constraints_in_raw_mode(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => 'Short content',
            'fr' => 'Contenu court',
        ])->create();

        $draft = CreationDraft::factory()->create([
            'name' => 'Test Draft for Height',
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($draft) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?draft-id='.$draft->id)
                ->waitForText('Markdown')
                ->pause(2000)
                ->click('[data-testid="toggle-raw-mode"]')
                ->pause(500)
                ->assertVisible('.raw-markdown-editor');

            // Check min-height
            $minHeight = $browser->script(
                'return window.getComputedStyle(document.querySelector(".raw-markdown-editor")).minHeight;'
            )[0];

            $this->assertEquals('200px', $minHeight, 'Raw markdown editor should have min-height of 200px');

            // Check max-height
            $maxHeight = $browser->script(
                'return window.getComputedStyle(document.querySelector(".raw-markdown-editor")).maxHeight;'
            )[0];

            $this->assertEquals('600px', $maxHeight, 'Raw markdown editor should have max-height of 600px');
        });
    }
}
