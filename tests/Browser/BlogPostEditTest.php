<?php

namespace Tests\Browser;

use App\Models\BlogCategory;
use App\Models\BlogPostDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BlogPostEditTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_debug_page_loading(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitForText("Modifier l'article")
                ->assertSee("Création d'un nouvel article");
        });
    }

    public function test_can_create_new_blog_post_draft(): void
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
                ->waitFor('[data-testid="blog-form"]', 10)
                    // Fill in basic blog post info
                ->click('[data-testid="blog-type-select"]')
                ->pause(500)
                ->click('*[data-value="article"]')
                ->type('[data-testid="blog-title-input"]', 'Test Article Title')
                ->click('[data-testid="blog-category-select"]')
                ->pause(500)
                ->click('*[data-value="'.$category->id.'"]')
                ->click('button:contains("Sauvegarder le brouillon")')
                ->waitFor('[data-testid="content-builder"]', 10)
                    // Try to add markdown content
                ->click('[data-testid="add-text-button"]')
                ->pause(3000) // Wait for potential AJAX errors
                ->assertDontSee('Erreur lors de l\'ajout du contenu');
        });
    }

    public function test_api_endpoints_work(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withFrenchName('Test Category')->create();

        $this->browse(function (Browser $browser) use ($user, $category) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 10)
                    // Fill basic info and save draft first
                ->click('[data-testid="blog-type-select"]')
                ->pause(500)
                ->click('*[data-value="article"]')
                ->type('[data-testid="blog-title-input"]', 'API Test Article')
                ->click('[data-testid="blog-category-select"]')
                ->pause(500)
                ->click('*[data-value="'.$category->id.'"]')
                ->click('button:contains("Sauvegarder le brouillon")')
                ->waitFor('[data-testid="content-builder"]', 10)
                ->pause(1000)
                    // Open browser console to check for network errors
                ->script([
                    'console.log("=== Testing API endpoints ===");',
                    'window.testApiCall = async function() {',
                    '  try {',
                    '    const response = await axios.post("/dashboard/api/blog-content-markdown", {',
                    '      content: "Test content",',
                    '      locale: "fr"',
                    '    });',
                    '    console.log("Markdown API success:", response.data);',
                    '    return "success";',
                    '  } catch (error) {',
                    '    console.error("Markdown API error:", error.response?.data);',
                    '    return error.response?.data || error.message;',
                    '  }',
                    '};',
                ])
                ->pause(500);

            // Execute the test and get result
            $result = $browser->script('return window.testApiCall();')[0];

            if ($result !== 'success') {
                $this->fail('API call failed: '.json_encode($result));
            }
        });
    }
}
