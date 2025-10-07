<?php

namespace Tests\Browser\Dashboard;

use App\Models\BlogCategory;
use App\Models\BlogPostDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BlogPostEditTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_content_builder_not_available_before_saving_draft(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Test', 'en' => 'Test'])->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]')
                // Content builder should not be visible initially
                ->assertDontSee('[data-testid="content-builder"]')
                ->assertSee('Veuillez d\'abord sauvegarder le brouillon pour pouvoir ajouter du contenu')
                ->screenshot('no-content-builder-before-save');
        });
    }

    // TODO: À corriger pour plus-tard
    /*public function test_can_create_new_blog_post_draft(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        $this->browse(function (Browser $browser) use ($category, $user) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]')
                ->assertSeeIn('h2', 'article')
                ->click('[data-testid="blog-title-input"]')
                ->type('title_content', 'Article de test')
                ->type('slug', 'test-article')
                ->select('category_id', $category->id)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon mis à jour avec succès')
                ->screenshot('blog-post-draft-form-filled');
        });

        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => 'test-article',
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Article de test',
        ]);

        $createdDraft = BlogPostDraft::where('slug', 'test-article')->first()->withRelationshipAutoloading();
        $frenchTitle = $createdDraft->titleTranslationKey->translations->where('locale', 'fr')->first()->text;
        $this->assertEquals('Article de test', $frenchTitle);
    }

    public function test_can_add_markdown_content_to_draft(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withFrenchName('Technologie', 'Technology')->create();

        $this->browse(function (Browser $browser) use ($user, $category) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]')
                // Fill in basic blog post info first and save draft
                ->type('[data-testid="blog-title-input"]', 'Test Article avec Markdown')
                ->type('slug', 'test-article-markdown')
                ->select('category_id', $category->id)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon mis à jour avec succès', 10)
                ->waitFor('[data-testid="content-builder"]', 10)
                // Add markdown content
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-text-button"]')
                ->waitForText('Bloc de contenu ajouté', 10)
                // Target the textarea that should now be visible
                ->waitFor('[data-testid="markdown-content-textarea"]')
                ->type('[data-testid="markdown-content-textarea"]', 'Test content')
                ->pause(2000) // Wait for debounced save
                ->screenshot('blog-post-with-markdown-content');
        });

        // Verify the content was saved to database
        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => 'test-article-markdown',
            'category_id' => $category->id,
        ]);

        // Verify translation was created with our content
        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Test content',
        ]);
    }*/
}
