<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\BlogContentGalleryController;
use App\Models\BlogContentGallery;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogContentGalleryController::class)]
class BlogContentGalleryControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function store_creates_gallery_without_pictures()
    {
        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => [],
            'captions' => [],
            'locale' => 'fr',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'created_at',
                'updated_at',
                'pictures',
            ]);

        $this->assertDatabaseCount('blog_content_galleries', 1);
        $gallery = BlogContentGallery::first();
        $this->assertNotNull($gallery);
        $this->assertCount(0, $gallery->pictures);
    }

    #[Test]
    public function store_creates_gallery_with_pictures_and_no_captions()
    {
        $pictures = Picture::factory()->count(3)->create();

        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => $pictures->pluck('id')->toArray(),
            'captions' => [],
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gallery = BlogContentGallery::first();
        $this->assertCount(3, $gallery->pictures);

        // Vérifier l'ordre des images
        $attachedPictures = $gallery->pictures;
        foreach ($attachedPictures as $index => $picture) {
            $this->assertEquals($index + 1, $picture->pivot->order);
            $this->assertNull($picture->pivot->caption_translation_key_id);
        }
    }

    #[Test]
    public function store_creates_gallery_with_pictures_and_captions()
    {
        $pictures = Picture::factory()->count(2)->create();
        $captions = ['Caption for first image', 'Caption for second image'];

        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => $pictures->pluck('id')->toArray(),
            'captions' => $captions,
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gallery = BlogContentGallery::first();
        $this->assertCount(2, $gallery->pictures);

        // Vérifier les captions
        foreach ($gallery->pictures as $index => $picture) {
            $this->assertNotNull($picture->pivot->caption_translation_key_id);
            $translationKey = TranslationKey::find($picture->pivot->caption_translation_key_id);
            $this->assertNotNull($translationKey);

            $frTranslation = $translationKey->translations()->where('locale', 'fr')->first();
            $this->assertEquals($captions[$index], $frTranslation->text);

            // Vérifier que la traduction EN est vide
            $enTranslation = $translationKey->translations()->where('locale', 'en')->first();
            $this->assertEquals('', $enTranslation->text);
        }
    }

    #[Test]
    public function store_fails_with_invalid_validation_data()
    {
        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => ['invalid_id'],
            'locale' => 'invalid_locale',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picture_ids.0', 'locale']);
    }

    #[Test]
    public function store_with_null_picture_ids_creates_empty_gallery()
    {
        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => null,
            'captions' => null,
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gallery = BlogContentGallery::first();
        $this->assertCount(0, $gallery->pictures);
    }

    #[Test]
    public function show_returns_gallery_with_pictures()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(2)->create();

        foreach ($pictures as $index => $picture) {
            $gallery->pictures()->attach($picture->id, [
                'order' => $index + 1,
            ]);
        }

        $response = $this->getJson("/dashboard/api/blog-content-gallery/{$gallery->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'created_at',
                'updated_at',
                'pictures' => [
                    '*' => [
                        'id',
                        'pivot' => [
                            'order',
                            'caption_translation_key_id',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('pictures'));
    }

    #[Test]
    public function update_replaces_gallery_pictures()
    {
        $gallery = BlogContentGallery::create();
        $oldPictures = Picture::factory()->count(2)->create();

        // Attacher les anciennes images avec des captions
        foreach ($oldPictures as $index => $picture) {
            $captionKey = TranslationKey::create(['key' => 'old_caption_'.$index]);
            $captionKey->translations()->create(['locale' => 'fr', 'text' => 'Old caption']);
            $captionKey->translations()->create(['locale' => 'en', 'text' => '']);

            $gallery->pictures()->attach($picture->id, [
                'order' => $index + 1,
                'caption_translation_key_id' => $captionKey->id,
            ]);
        }

        // Nouvelles images
        $newPictures = Picture::factory()->count(3)->create();

        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'picture_ids' => $newPictures->pluck('id')->toArray(),
            'captions' => ['New caption 1', '', 'New caption 3'],
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $this->assertCount(3, $gallery->pictures);

        // Vérifier que les anciennes clés de traduction ont été supprimées
        $this->assertDatabaseMissing('translation_keys', ['key' => 'old_caption_0']);
        $this->assertDatabaseMissing('translation_keys', ['key' => 'old_caption_1']);
    }

    #[Test]
    public function update_removes_all_pictures()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(2)->create();

        foreach ($pictures as $index => $picture) {
            $gallery->pictures()->attach($picture->id, ['order' => $index + 1]);
        }

        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'picture_ids' => [],
            'captions' => [],
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $this->assertCount(0, $gallery->pictures);
    }

    #[Test]
    public function update_fails_with_validation_errors()
    {
        $gallery = BlogContentGallery::create();

        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'picture_ids' => ['invalid'],
            'locale' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picture_ids.0', 'locale']);
    }

    #[Test]
    public function update_with_null_values_removes_all_pictures()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(2)->create();

        foreach ($pictures as $index => $picture) {
            $gallery->pictures()->attach($picture->id, ['order' => $index + 1]);
        }

        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'picture_ids' => null,
            'captions' => null,
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $this->assertCount(0, $gallery->pictures);
    }

    #[Test]
    public function update_pictures_updates_pictures_order_and_captions()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(3)->create();

        // Attacher les images initiales
        foreach ($pictures as $index => $picture) {
            $gallery->pictures()->attach($picture->id, ['order' => $index + 1]);
        }

        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'pictures' => [
                ['id' => $pictures[2]->id, 'caption' => 'Third becomes first', 'order' => 1],
                ['id' => $pictures[0]->id, 'caption' => 'First becomes second', 'order' => 2],
                ['id' => $pictures[1]->id, 'caption' => '', 'order' => 3],
            ],
            'locale' => 'en',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $orderedPictures = $gallery->pictures;

        // Vérifier l'ordre
        $this->assertEquals($pictures[2]->id, $orderedPictures[0]->id);
        $this->assertEquals($pictures[0]->id, $orderedPictures[1]->id);
        $this->assertEquals($pictures[1]->id, $orderedPictures[2]->id);

        // Vérifier les captions
        $this->assertNotNull($orderedPictures[0]->pivot->caption_translation_key_id);
        $this->assertNotNull($orderedPictures[1]->pivot->caption_translation_key_id);
        $this->assertNull($orderedPictures[2]->pivot->caption_translation_key_id);

        // Vérifier le contenu des traductions
        $firstCaption = TranslationKey::find($orderedPictures[0]->pivot->caption_translation_key_id);
        $enTranslation = $firstCaption->translations()->where('locale', 'en')->first();
        $this->assertEquals('Third becomes first', $enTranslation->text);
    }

    #[Test]
    public function update_pictures_fails_with_validation_errors()
    {
        $gallery = BlogContentGallery::create();

        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'pictures' => [
                ['id' => 'invalid', 'order' => 'not_a_number'],
            ],
            'locale' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pictures.0.id', 'pictures.0.order', 'locale']);
    }

    #[Test]
    public function update_pictures_requires_pictures_array()
    {
        $gallery = BlogContentGallery::create();

        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'locale' => 'fr',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pictures']);
    }

    #[Test]
    public function update_pictures_with_minimum_required_fields()
    {
        $gallery = BlogContentGallery::create();
        $picture = Picture::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'pictures' => [
                ['id' => $picture->id, 'order' => 1],
            ],
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $this->assertCount(1, $gallery->pictures);
        $this->assertEquals($picture->id, $gallery->pictures->first()->id);
        $this->assertEquals(1, $gallery->pictures->first()->pivot->order);
        $this->assertNull($gallery->pictures->first()->pivot->caption_translation_key_id);
    }

    #[Test]
    public function destroy_deletes_gallery_with_caption_cleanup()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(2)->create();

        // Ajouter des images avec des captions
        $captionKeys = [];
        foreach ($pictures as $index => $picture) {
            $captionKey = TranslationKey::create(['key' => 'caption_'.$index]);
            $captionKey->translations()->create(['locale' => 'fr', 'text' => 'Caption '.$index]);
            $captionKey->translations()->create(['locale' => 'en', 'text' => 'Caption '.$index]);
            $captionKeys[] = $captionKey->id;

            $gallery->pictures()->attach($picture->id, [
                'order' => $index + 1,
                'caption_translation_key_id' => $captionKey->id,
            ]);
        }

        $response = $this->deleteJson("/dashboard/api/blog-content-gallery/{$gallery->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Gallery deleted successfully']);

        // Vérifier que la galerie est supprimée
        $this->assertDatabaseMissing('blog_content_galleries', ['id' => $gallery->id]);

        // Vérifier que les clés de traduction sont supprimées
        foreach ($captionKeys as $keyId) {
            $this->assertDatabaseMissing('translation_keys', ['id' => $keyId]);
            $this->assertDatabaseMissing('translations', ['translation_key_id' => $keyId]);
        }

        // Vérifier que les images existent toujours
        foreach ($pictures as $picture) {
            $this->assertDatabaseHas('pictures', ['id' => $picture->id]);
        }
    }

    #[Test]
    public function destroy_empty_gallery_success()
    {
        $gallery = BlogContentGallery::create();

        $response = $this->deleteJson("/dashboard/api/blog-content-gallery/{$gallery->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Gallery deleted successfully']);

        // Vérifier que la galerie est supprimée
        $this->assertDatabaseMissing('blog_content_galleries', ['id' => $gallery->id]);
    }

    #[Test]
    public function store_with_empty_captions_does_not_create_translation_keys()
    {
        $pictures = Picture::factory()->count(2)->create();

        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'picture_ids' => $pictures->pluck('id')->toArray(),
            'captions' => ['', null],
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gallery = BlogContentGallery::first();

        // Vérifier qu'aucune clé de traduction n'a été créée
        foreach ($gallery->pictures as $picture) {
            $this->assertNull($picture->pivot->caption_translation_key_id);
        }

        $this->assertDatabaseCount('translation_keys', 0);
    }

    #[Test]
    public function update_with_mixed_captions_handles_correctly()
    {
        $gallery = BlogContentGallery::create();
        $pictures = Picture::factory()->count(3)->create();

        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'picture_ids' => $pictures->pluck('id')->toArray(),
            'captions' => ['First caption', '', 'Third caption'],
            'locale' => 'en',
        ]);

        $response->assertOk();

        $gallery->refresh();
        $attachedPictures = $gallery->pictures;

        // Première image : avec caption
        $this->assertNotNull($attachedPictures[0]->pivot->caption_translation_key_id);

        // Deuxième image : sans caption
        $this->assertNull($attachedPictures[1]->pivot->caption_translation_key_id);

        // Troisième image : avec caption
        $this->assertNotNull($attachedPictures[2]->pivot->caption_translation_key_id);

        // Vérifier le nombre de clés de traduction créées
        $this->assertDatabaseCount('translation_keys', 2);
    }

    #[Test]
    public function update_pictures_with_empty_caption_removes_translation_key()
    {
        $gallery = BlogContentGallery::create();
        $picture = Picture::factory()->create();

        // Attacher avec une caption
        $captionKey = TranslationKey::create(['key' => 'initial_caption']);
        $captionKey->translations()->create(['locale' => 'fr', 'text' => 'Caption initiale']);
        $captionKey->translations()->create(['locale' => 'en', 'text' => 'Initial caption']);

        $gallery->pictures()->attach($picture->id, [
            'order' => 1,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        // Mettre à jour avec une caption vide
        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'pictures' => [
                ['id' => $picture->id, 'caption' => '', 'order' => 1],
            ],
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $gallery->refresh();

        // Vérifier que la caption a été supprimée
        $this->assertNull($gallery->pictures->first()->pivot->caption_translation_key_id);

        // Vérifier que l'ancienne clé de traduction a été supprimée
        $this->assertDatabaseMissing('translation_keys', ['id' => $captionKey->id]);
    }

    #[Test]
    public function routes_require_authentication()
    {
        $this->app['auth']->forgetGuards();

        $gallery = BlogContentGallery::create();

        // Test store
        $response = $this->postJson('/dashboard/api/blog-content-gallery', [
            'locale' => 'fr',
        ]);
        $response->assertUnauthorized();

        // Test show
        $response = $this->getJson("/dashboard/api/blog-content-gallery/{$gallery->id}");
        $response->assertUnauthorized();

        // Test update
        $response = $this->putJson("/dashboard/api/blog-content-gallery/{$gallery->id}", [
            'locale' => 'fr',
        ]);
        $response->assertUnauthorized();

        // Test updatePictures
        $response = $this->putJson("/dashboard/api/blog-content-galleries/{$gallery->id}/pictures", [
            'locale' => 'fr',
        ]);
        $response->assertUnauthorized();

        // Test destroy
        $response = $this->deleteJson("/dashboard/api/blog-content-gallery/{$gallery->id}");
        $response->assertUnauthorized();
    }
}
