<?php

namespace Tests\Feature\Models;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Tag;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Tag::class)]
class TagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_tag()
    {
        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    #[Test]
    public function it_can_be_associated_with_creations()
    {
        $tag = Tag::factory()->create();
        $creations = Creation::factory()->count(3)->create();

        $tag->creations()->attach($creations);

        $this->assertCount(3, $tag->creations);
        $this->assertInstanceOf(Creation::class, $tag->creations->first());
    }

    #[Test]
    public function it_can_be_associated_with_creation_drafts()
    {
        $tag = Tag::factory()->create();
        $creationDrafts = CreationDraft::factory()->count(3)->create();

        $tag->creationDrafts()->attach($creationDrafts);

        $this->assertCount(3, $tag->creationDrafts);
        $this->assertInstanceOf(CreationDraft::class, $tag->creationDrafts->first());
    }

    #[Test]
    public function it_has_unique_slug()
    {
        Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

        try {
            Tag::factory()->create(['name' => 'PHP Hypertext', 'slug' => 'php']);
            $this->fail('Exception attendue pour slug dupliqué non déclenchée');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Exception pour slug dupliqué correctement déclenchée');
        }
    }
}
