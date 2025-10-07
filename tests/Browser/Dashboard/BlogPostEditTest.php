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
                ->waitFor('[data-testid="blog-form"]', 15)
                // Content builder should not be visible initially
                ->assertDontSee('[data-testid="content-builder"]')
                ->assertSee('Veuillez d\'abord sauvegarder le brouillon pour pouvoir ajouter du contenu')
                ->screenshot('no-content-builder-before-save');
        });
    }

    public function test_can_create_new_blog_post_draft(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                ->assertSee('Nouvel article')
                // Fill in title
                ->type('[data-testid="blog-title-input"]', 'Article de test')
                ->pause(1000)
                // Fill in slug (it should be auto-generated, but we'll override it)
                ->clear('slug')
                ->type('slug', 'test-article')
                ->pause(500)
                // Select category (Shadcn/Reka UI Select component)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                // Submit form
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 20)
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
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        $this->browse(function (Browser $browser) use ($user, $category) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                // Fill in basic blog post info first and save draft
                ->type('[data-testid="blog-title-input"]', 'Test Article avec Markdown')
                ->pause(1000)
                ->clear('slug')
                ->type('slug', 'test-article-markdown')
                ->pause(500)
                // Select category (Shadcn/Reka UI Select component)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                // Submit form
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 20)
                ->waitFor('[data-testid="content-builder"]', 10)
                // Add markdown content
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-text-button"]')
                ->pause(2000)
                // Wait for textarea and fill it with JavaScript to properly trigger Vue events
                ->waitFor('[data-slot="textarea"]', 15);

            // Use JavaScript to set value and trigger proper Vue events
            $browser->script("
                const textarea = document.querySelector('[data-slot=\"textarea\"]');
                if (textarea) {
                    textarea.value = 'Test content';
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    textarea.blur();
                    textarea.focus();
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                }
            ");

            $browser
                // Wait for debounced save (1.5s debounce + extra time)
                ->pause(4000)
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
    }

    /**
     * Helper method to create a temporary 1x1 pixel PNG image for testing uploads
     */
    private function createTestImage(string $filename = 'test-image.png'): string
    {
        $tempDir = sys_get_temp_dir();
        $filepath = $tempDir.'/'.$filename;

        // Create a 1x1 pixel transparent PNG
        $image = imagecreatetruecolor(1, 1);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $filepath);
        imagedestroy($image);

        return $filepath;
    }

    public function test_can_add_gallery_with_single_image_without_caption(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        // Create a test image
        $imagePath = $this->createTestImage('gallery-test-1.png');

        $this->browse(function (Browser $browser) use ($user, $category, $imagePath) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                // Fill in basic blog post info and save draft
                ->type('[data-testid="blog-title-input"]', 'Article avec Galerie')
                ->pause(1000)
                ->clear('slug')
                ->type('slug', 'article-galerie-test')
                ->pause(500)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 10)
                ->waitFor('[data-testid="content-builder"]', 10)
                // Add gallery content (optimistic UI)
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-gallery-button"]')
                ->pause(500) // Gallery block appears instantly
                // Wait for the gallery to be ready (API calls complete)
                ->waitFor('[data-status="ready"]', 15)
                ->assertVisible('[data-testid="gallery-manager"]')
                ->assertVisible('[data-testid="gallery-upload-zone"]');

            // Give the component a moment to fully initialize after becoming ready
            $browser->pause(1000);

            // Upload image using Dusk's native attach method
            $browser->attach('[data-testid="gallery-file-input"]', $imagePath)
                // Wait for upload success toast
                ->waitForText('ajoutée avec succès', 10)
                ->pause(2000) // Wait a bit after upload
                // The gallery auto-saves after upload, wait for it
                ->pause(2000) // Wait for auto-save (1s debounce + margin)
                ->screenshot('gallery-single-image-no-caption');
        });

        // Cleanup
        @unlink($imagePath);

        // Verify the content was saved to database
        $draft = \App\Models\BlogPostDraft::where('slug', 'article-galerie-test')->first();
        $this->assertNotNull($draft);

        // Verify gallery content was created
        $galleryContent = $draft->contents()
            ->where('content_type', 'App\Models\BlogContentGallery')
            ->first();
        $this->assertNotNull($galleryContent);

        // Verify the gallery has 1 image
        $gallery = $galleryContent->content;
        $this->assertNotNull($gallery);
        $this->assertCount(1, $gallery->pictures);

        // Verify the image has no caption
        $pivot = $gallery->pictures->first()->pivot;
        $this->assertNull($pivot->caption_translation_key_id);
        $this->assertEquals(1, $pivot->order);
    }

    public function test_can_add_gallery_with_single_image_with_caption(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        // Create a test image
        $imagePath = $this->createTestImage('gallery-test-caption.png');

        $this->browse(function (Browser $browser) use ($user, $category, $imagePath) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                // Fill in basic blog post info and save draft
                ->type('[data-testid="blog-title-input"]', 'Article avec Image et Caption')
                ->pause(1000)
                ->clear('slug')
                ->type('slug', 'article-image-caption-test')
                ->pause(500)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 20)
                ->waitFor('[data-testid="content-builder"]', 10)
                // Add gallery content
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-gallery-button"]')
                ->pause(500)
                ->waitFor('[data-status="ready"]', 25)
                ->assertVisible('[data-testid="gallery-manager"]')
                ->assertVisible('[data-testid="gallery-upload-zone"]')
                // Give the component a moment to fully initialize
                ->pause(1000)
                // Upload image
                ->attach('[data-testid="gallery-file-input"]', $imagePath)
                ->waitForText('ajoutée avec succès', 10)
                // Wait for the upload auto-save to complete (1s debounce + API call time)
                ->pause(3000)
                // Now the image card should be visible, wait for it
                ->waitFor('[data-testid="gallery-image-caption-0"]', 5);

            // Add caption to the uploaded image using JavaScript (same approach as markdown test)
            $browser->script("
                const textarea = document.querySelector('[data-testid=\"gallery-image-caption-0\"]');
                if (textarea) {
                    textarea.value = 'Ceci est une description de test';
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    textarea.blur();
                    textarea.focus();
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                }
            ");

            $browser
                // Wait for caption auto-save (1s debounce + API call time)
                ->pause(4000)
                ->screenshot('gallery-single-image-with-caption');
        });

        // Cleanup
        @unlink($imagePath);

        // Verify the content was saved to database
        $draft = \App\Models\BlogPostDraft::where('slug', 'article-image-caption-test')->first();
        $this->assertNotNull($draft);

        // Verify gallery content was created
        $galleryContent = $draft->contents()
            ->where('content_type', 'App\\Models\\BlogContentGallery')
            ->first();
        $this->assertNotNull($galleryContent);

        // Verify the gallery has 1 image
        $gallery = $galleryContent->content;
        $this->assertNotNull($gallery);
        $this->assertCount(1, $gallery->pictures);

        // Verify the image has a caption
        $pivot = $gallery->pictures->first()->pivot;
        $this->assertNotNull($pivot->caption_translation_key_id);

        // Verify the caption text
        $captionTranslation = \App\Models\Translation::where('translation_key_id', $pivot->caption_translation_key_id)
            ->where('locale', 'fr')
            ->first();
        $this->assertNotNull($captionTranslation);
        $this->assertEquals('Ceci est une description de test', $captionTranslation->text);
    }

    public function test_can_remove_image_from_gallery(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        // Create two test images
        $imagePath1 = $this->createTestImage('gallery-remove-1.png');
        $imagePath2 = $this->createTestImage('gallery-remove-2.png');

        $this->browse(function (Browser $browser) use ($user, $category, $imagePath1, $imagePath2) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                // Fill in basic blog post info and save draft
                ->type('[data-testid="blog-title-input"]', 'Test Suppression Image')
                ->pause(1000)
                ->clear('slug')
                ->type('slug', 'test-suppression-image')
                ->pause(500)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 10)
                ->waitFor('[data-testid="content-builder"]', 10)
                // Wait a bit more to ensure draft is fully saved
                ->pause(2000)
                // Add gallery content
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-gallery-button"]')
                ->pause(500)
                ->waitFor('[data-status="ready"]', 25)
                ->assertVisible('[data-testid="gallery-manager"]')
                ->pause(1000)
                // Upload first image
                ->attach('[data-testid="gallery-file-input"]', $imagePath1)
                ->waitForText('ajoutée avec succès', 10)
                ->waitFor('[data-testid="gallery-image-caption-0"]', 5)
                ->pause(3000) // Wait for auto-save
                // Upload second image
                ->attach('[data-testid="gallery-file-input"]', $imagePath2)
                ->waitForText('ajoutée avec succès', 10)
                ->waitFor('[data-testid="gallery-image-caption-1"]', 10)
                ->pause(3000) // Wait for auto-save
                // Verify both images are visible
                ->assertVisible('[data-testid="gallery-image-caption-0"]')
                ->assertVisible('[data-testid="gallery-image-caption-1"]')
                ->screenshot('gallery-before-remove');

            // Remove the first image by hovering over it and clicking the remove button
            // The remove button becomes visible on hover
            $browser->script("
                // Find the first image card and trigger hover
                const imageCard = document.querySelector('[data-testid=\"gallery-image-caption-0\"]').closest('.group');
                const removeButton = imageCard.querySelector('[data-testid=\"gallery-remove-image-button\"]');
                if (removeButton) {
                    removeButton.click();
                }
            ");

            $browser
                ->waitForText('Image supprimée', 5)
                // Wait for auto-save after removal (1s debounce + API call time)
                ->pause(4000)
                // Verify only one caption textarea remains
                ->assertVisible('[data-testid="gallery-image-caption-0"]')
                ->assertMissing('[data-testid="gallery-image-caption-1"]')
                ->screenshot('gallery-after-remove');
        });

        // Cleanup
        @unlink($imagePath1);
        @unlink($imagePath2);

        // Verify the content was saved to database
        $draft = \App\Models\BlogPostDraft::where('slug', 'test-suppression-image')->first();
        $this->assertNotNull($draft);

        // Verify gallery content exists
        $galleryContent = $draft->contents()
            ->where('content_type', 'App\\Models\\BlogContentGallery')
            ->first();
        $this->assertNotNull($galleryContent);

        // Verify the gallery now has only 1 image
        $gallery = $galleryContent->content;
        $this->assertNotNull($gallery);
        $this->assertCount(1, $gallery->pictures);
    }

    public function test_can_edit_caption_of_existing_image(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::factory()->withNames(['fr' => 'Technologie', 'en' => 'Technology'])->create();

        // Create a test image
        $imagePath = $this->createTestImage('gallery-edit-caption.png');

        $this->browse(function (Browser $browser) use ($user, $category, $imagePath) {
            $browser->loginAs($user)
                ->visit('/dashboard/blog-posts/edit')
                ->waitFor('[data-testid="blog-form"]', 15)
                // Fill in basic blog post info and save draft
                ->type('[data-testid="blog-title-input"]', 'Test Edition Caption')
                ->pause(1000)
                ->clear('slug')
                ->type('slug', 'test-edition-caption')
                ->pause(500)
                ->click('[data-testid="blog-category-select"]')
                ->pause(1000)
                ->click('[data-slot="select-item"]')
                ->pause(1000)
                ->press('Sauvegarder le brouillon')
                ->waitForText('Brouillon créé avec succès', 10)
                ->waitFor('[data-testid="content-builder"]', 10)
                ->pause(2000)
                // Add gallery content
                ->assertVisible('[data-testid="content-builder"]')
                ->click('[data-testid="add-gallery-button"]')
                ->pause(500)
                ->waitFor('[data-status="ready"]', 25)
                ->assertVisible('[data-testid="gallery-manager"]')
                ->assertVisible('[data-testid="gallery-upload-zone"]')
                ->pause(2000) // Extra time to ensure component is fully initialized
                ->screenshot('before-upload-edit-caption')
                // Upload image
                ->attach('[data-testid="gallery-file-input"]', $imagePath)
                ->pause(1000) // Brief pause after attach
                ->screenshot('after-attach-edit-caption')
                ->waitForText('ajoutée avec succès', 15)
                ->waitFor('[data-testid="gallery-image-caption-0"]', 5)
                ->pause(3000); // Wait for auto-save

            // Add initial caption
            $browser->script("
                const textarea = document.querySelector('[data-testid=\"gallery-image-caption-0\"]');
                if (textarea) {
                    textarea.value = 'Caption initiale';
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    textarea.blur();
                    textarea.focus();
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                }
            ");

            $browser
                ->pause(4000) // Wait for caption auto-save
                ->screenshot('gallery-initial-caption');

            // Edit the caption to a new value
            $browser->script("
                const textarea = document.querySelector('[data-testid=\"gallery-image-caption-0\"]');
                if (textarea) {
                    textarea.value = 'Caption modifiée avec succès';
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    textarea.blur();
                    textarea.focus();
                    textarea.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                }
            ");

            $browser
                ->pause(4000) // Wait for caption auto-save
                ->screenshot('gallery-edited-caption');
        });

        // Cleanup
        @unlink($imagePath);

        // Verify the content was saved to database
        $draft = \App\Models\BlogPostDraft::where('slug', 'test-edition-caption')->first();
        $this->assertNotNull($draft);

        // Verify gallery content was created
        $galleryContent = $draft->contents()
            ->where('content_type', 'App\\Models\\BlogContentGallery')
            ->first();
        $this->assertNotNull($galleryContent);

        // Verify the gallery has 1 image
        $gallery = $galleryContent->content;
        $this->assertNotNull($gallery);
        $this->assertCount(1, $gallery->pictures);

        // Verify the image has a caption
        $pivot = $gallery->pictures->first()->pivot;
        $this->assertNotNull($pivot->caption_translation_key_id);

        // Verify the caption text was updated to the new value
        $captionTranslation = \App\Models\Translation::where('translation_key_id', $pivot->caption_translation_key_id)
            ->where('locale', 'fr')
            ->first();
        $this->assertNotNull($captionTranslation);
        $this->assertEquals('Caption modifiée avec succès', $captionTranslation->text);
    }
}
