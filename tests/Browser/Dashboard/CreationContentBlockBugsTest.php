<?php

declare(strict_types=1);

namespace Tests\Browser\Dashboard;

use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

class CreationContentBlockBugsTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_copies_content_blocks_when_creating_draft_from_creation(): void
    {
        // Create a creation with content blocks
        $creation = Creation::factory()->create([
            'name' => 'Test Creation With Content',
        ]);

        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => 'This is the markdown content for the creation.',
            'fr' => 'Ceci est le contenu markdown pour la création.',
        ])->create();

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?creation-id='.$creation->id)
                ->waitForText('Markdown')
                ->pause(3000) // Wait for content to load
                ->assertSee('Ceci est le contenu markdown pour la création'); // French locale

            // Verify in database that a draft was created with content blocks
            $draft = CreationDraft::where('original_creation_id', $creation->id)->first();
            $this->assertNotNull($draft);
            $this->assertCount(1, $draft->contents);
            $this->assertEquals(ContentMarkdown::class, $draft->contents->first()->content_type);
        });
    }

    #[Test]
    public function it_preserves_existing_content_blocks_when_editing(): void
    {
        // Create a draft that already has content blocks
        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => 'Existing content block.',
            'fr' => 'Contenu existant du bloc.',
        ])->create();

        $draft = CreationDraft::factory()->create([
            'name' => 'Draft With Existing Content',
            'full_description_translation_key_id' => $translationKey->id,
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->assertCount(1, $draft->contents);
        $originalContentId = $draft->contents->first()->content_id;

        $this->browse(function (Browser $browser) use ($draft, $originalContentId) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?draft-id='.$draft->id)
                ->waitForText('Markdown')
                ->pause(3000) // Wait for content to load
                ->assertSee('Contenu existant du bloc'); // French locale

            $draft->refresh();
            // Should not have created additional content blocks
            $this->assertCount(1, $draft->contents);
            $this->assertEquals($originalContentId, $draft->contents->first()->content_id);
        });
    }

    #[Test]
    public function it_displays_multiple_content_blocks_after_copying(): void
    {
        // Create a creation with multiple content blocks
        $creation = Creation::factory()->create([
            'name' => 'Creation With Multiple Blocks',
        ]);

        // Create first markdown block
        $translationKey1 = TranslationKey::factory()->withTranslations([
            'en' => 'First markdown block content.',
            'fr' => 'Contenu du premier bloc markdown.',
        ])->create();

        $markdown1 = ContentMarkdown::create([
            'translation_key_id' => $translationKey1->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);

        // Create second markdown block
        $translationKey2 = TranslationKey::factory()->withTranslations([
            'en' => 'Second markdown block content.',
            'fr' => 'Contenu du deuxième bloc markdown.',
        ])->create();

        $markdown2 = ContentMarkdown::create([
            'translation_key_id' => $translationKey2->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?creation-id='.$creation->id)
                ->waitForText('Markdown')
                ->pause(3000) // Wait for content to load
                ->assertSee('Contenu du premier bloc markdown') // French locale
                ->assertSee('Contenu du deuxième bloc markdown'); // French locale

            // Verify in database that draft has both content blocks
            $draft = CreationDraft::where('original_creation_id', $creation->id)->first();
            $this->assertNotNull($draft);
            $this->assertCount(2, $draft->contents);

            $contents = $draft->contents->sortBy('order')->values();
            $this->assertEquals(1, $contents[0]->order);
            $this->assertEquals(2, $contents[1]->order);
        });
    }
}
