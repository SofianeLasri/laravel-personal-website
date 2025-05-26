<?php

namespace Tests\Feature\Models\Certification;

use App\Models\Certification;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Certification::class)]
class CertificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_certification()
    {
        $picture = Picture::factory()->create();

        $certification = Certification::create([
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => $picture->id,
        ]);

        $this->assertDatabaseHas('certifications', [
            'id' => $certification->id,
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15 00:00:00',
            'link' => 'https://laravel.com/certification',
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function it_can_have_a_picture()
    {
        $picture = Picture::factory()->create();
        $certification = Certification::factory()->create(['picture_id' => $picture->id]);

        $this->assertInstanceOf(Picture::class, $certification->picture);
        $this->assertEquals($picture->id, $certification->picture->id);
    }

    #[Test]
    public function it_casts_date_field_correctly()
    {
        $certification = Certification::factory()->create([
            'date' => '2024-05-15',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $certification->date);
        $this->assertEquals('2024-05-15', $certification->date->format('Y-m-d'));
    }

    #[Test]
    public function it_does_not_have_timestamps()
    {
        $certification = new Certification;

        $this->assertFalse($certification->timestamps);
    }

    #[Test]
    public function it_can_be_sorted_by_date()
    {
        $older = Certification::factory()->create([
            'date' => '2023-01-01',
        ]);

        $newer = Certification::factory()->create([
            'date' => '2024-01-01',
        ]);

        $certifications = Certification::orderBy('date', 'desc')->get();

        $this->assertEquals($newer->id, $certifications->first()->id);
        $this->assertEquals($older->id, $certifications->last()->id);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $certification = new Certification;

        $expected = [
            'name',
            'score',
            'date',
            'link',
            'picture_id',
        ];

        $this->assertEquals($expected, $certification->getFillable());
    }
}
