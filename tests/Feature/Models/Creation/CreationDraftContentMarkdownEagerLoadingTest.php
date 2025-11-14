<?php

declare(strict_types=1);

namespace Tests\Feature\Models\Creation;

use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreationDraftContentMarkdownEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_loads_translation_key_and_translations_when_creating_draft_from_creation(): void
    {
        $user = User::factory()->create();
        $creation = Creation::factory()->create();

        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => 'This is English markdown content',
            'fr' => 'Ceci est du contenu markdown français',
        ])->create();

        $markdownContent = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Visit the edit page with creation-id parameter to create draft
        $response = $this->actingAs($user)
            ->get(route('dashboard.creations.edit', ['creation-id' => $creation->id]));

        $response->assertStatus(200);

        // Verify that the translationKey and translations are loaded in the Inertia props
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/creations/EditPage')
            ->has('creationDraft.contents', 1)
            ->where('creationDraft.contents.0.content_type', ContentMarkdown::class)
            ->has('creationDraft.contents.0.content.translation_key', fn (Assert $translationKey) => $translationKey
                ->has('id')
                ->has('translations', 2)
                ->where('translations.0.locale', 'en')
                ->where('translations.0.text', 'This is English markdown content')
                ->where('translations.1.locale', 'fr')
                ->where('translations.1.text', 'Ceci est du contenu markdown français')
                ->etc()
            )
        );
    }

    #[Test]
    public function it_loads_translation_key_and_translations_when_loading_existing_draft(): void
    {
        $user = User::factory()->create();
        $creation = Creation::factory()->create();

        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => 'Draft markdown content in English',
            'fr' => 'Contenu markdown du brouillon en français',
        ])->create();

        $markdownContent = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Create draft from creation
        $draft = CreationDraft::fromCreation($creation);

        // Now visit the edit page with the draft-id
        $response = $this->actingAs($user)
            ->get(route('dashboard.creations.edit', ['draft-id' => $draft->id]));

        $response->assertStatus(200);

        // Verify that the translationKey and translations are loaded in the Inertia props
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/creations/EditPage')
            ->has('creationDraft.contents', 1)
            ->where('creationDraft.contents.0.content_type', ContentMarkdown::class)
            ->has('creationDraft.contents.0.content.translation_key', fn (Assert $translationKey) => $translationKey
                ->has('id')
                ->has('translations', 2)
                // Note: The translations in the draft are duplicated from the original,
                // so we just verify they exist and have the correct structure
                ->has('translations.0', fn (Assert $translation) => $translation
                    ->has('id')
                    ->has('locale')
                    ->has('text')
                    ->etc()
                )
                ->has('translations.1', fn (Assert $translation) => $translation
                    ->has('id')
                    ->has('locale')
                    ->has('text')
                    ->etc()
                )
                ->etc()
            )
        );
    }

    #[Test]
    public function it_preserves_markdown_translations_when_copying_content_blocks(): void
    {
        $creation = Creation::factory()->create();

        $translationKey = TranslationKey::factory()->withTranslations([
            'en' => '## Markdown Heading\n\nContent with **bold** text.',
            'fr' => '## Titre Markdown\n\nContenu avec du texte **gras**.',
        ])->create();

        $markdownContent = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Create draft and load with eager loading
        $draft = CreationDraft::fromCreation($creation);
        $draft->load('contents.content.translationKey.translations');

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();

        $this->assertInstanceOf(ContentMarkdown::class, $draftContent->content);

        // Verify the translationKey relation is loaded
        $this->assertNotNull($draftContent->content->translationKey);
        $this->assertCount(2, $draftContent->content->translationKey->translations);

        // Verify translations are loaded correctly
        $enTranslation = $draftContent->content->translationKey->translations
            ->firstWhere('locale', 'en');
        $frTranslation = $draftContent->content->translationKey->translations
            ->firstWhere('locale', 'fr');

        $this->assertNotNull($enTranslation);
        $this->assertNotNull($frTranslation);

        $this->assertStringContainsString('Markdown Heading', $enTranslation->text);
        $this->assertStringContainsString('Titre Markdown', $frTranslation->text);
        $this->assertStringContainsString('**bold**', $enTranslation->text);
        $this->assertStringContainsString('**gras**', $frTranslation->text);
    }

    #[Test]
    public function it_handles_multiple_markdown_content_blocks_with_translations(): void
    {
        $user = User::factory()->create();
        $creation = Creation::factory()->create();

        // Create first markdown block
        $translationKey1 = TranslationKey::factory()->withTranslations([
            'en' => 'First markdown block',
            'fr' => 'Premier bloc markdown',
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
            'en' => 'Second markdown block',
            'fr' => 'Deuxième bloc markdown',
        ])->create();

        $markdown2 = ContentMarkdown::create([
            'translation_key_id' => $translationKey2->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);

        // Create draft from creation
        $draft = CreationDraft::fromCreation($creation);

        // Visit the edit page
        $response = $this->actingAs($user)
            ->get(route('dashboard.creations.edit', ['draft-id' => $draft->id]));

        $response->assertStatus(200);

        // Verify both content blocks have translations loaded
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/creations/EditPage')
            ->has('creationDraft.contents', 2)
            // First block
            ->has('creationDraft.contents.0.content.translation_key.translations', 2)
            ->has('creationDraft.contents.0.content.translation_key.translations.0.text')
            ->has('creationDraft.contents.0.content.translation_key.translations.1.text')
            // Second block
            ->has('creationDraft.contents.1.content.translation_key.translations', 2)
            ->has('creationDraft.contents.1.content.translation_key.translations.0.text')
            ->has('creationDraft.contents.1.content.translation_key.translations.1.text')
        );
    }
}
