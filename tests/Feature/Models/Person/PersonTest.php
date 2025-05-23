<?php

namespace Tests\Feature\Models\Person;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Person;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Person::class)]
class PersonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_person()
    {
        $person = Person::factory()->create([
            'name' => 'John Doe',
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'John Doe',
        ]);
    }

    #[Test]
    public function it_can_create_a_person_with_url()
    {
        $person = Person::factory()->create([
            'name' => 'John Doe',
            'url' => 'https://johndoe.com',
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'John Doe',
            'url' => 'https://johndoe.com',
        ]);
    }

    #[Test]
    public function it_can_have_a_picture()
    {
        $picture = Picture::factory()->create();

        $person = Person::factory()->create([
            'picture_id' => $picture->id,
        ]);

        $this->assertInstanceOf(Picture::class, $person->picture);
        $this->assertEquals($picture->id, $person->picture->id);
    }

    #[Test]
    public function it_can_be_associated_with_multiple_creations()
    {
        $person = Person::factory()->create();
        $creations = Creation::factory()->count(3)->create();

        $person->creations()->attach($creations);

        $this->assertCount(3, $person->creations);
        $this->assertInstanceOf(Creation::class, $person->creations->first());
    }

    #[Test]
    public function it_can_be_associated_with_multiple_creation_drafts()
    {
        $person = Person::factory()->create();
        $creationDrafts = CreationDraft::factory()->count(3)->create();

        $person->creationDrafts()->attach($creationDrafts);

        $this->assertCount(3, $person->creationDrafts);
        $this->assertInstanceOf(CreationDraft::class, $person->creationDrafts->first());
    }

    #[Test]
    public function updating_person_updates_across_all_creations()
    {
        $person = Person::factory()->create(['name' => 'John Doe']);
        $creations = Creation::factory()->count(2)->create();

        $person->creations()->attach($creations);

        $person->update(['name' => 'Jane Smith']);

        $creationOne = Creation::find($creations[0]->id);
        $creationTwo = Creation::find($creations[1]->id);

        $this->assertEquals('Jane Smith', $creationOne->people->first()->name);
        $this->assertEquals('Jane Smith', $creationTwo->people->first()->name);
    }
}
