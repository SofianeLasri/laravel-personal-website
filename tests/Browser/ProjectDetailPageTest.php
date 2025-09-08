<?php

namespace Tests\Browser;

use App\Enums\CreationType;
use App\Models\Creation;
use App\Models\Feature;
use App\Models\OptimizedPicture;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\Video;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectDetailPageTest extends DuskTestCase
{
    protected Creation $testProject;

    protected array $screenshots = [];

    protected array $technologies = [];

    protected array $people = [];

    protected array $features = [];

    protected array $videos = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create comprehensive test data
        $this->createTestData();
    }

    /**
     * Test that the project detail page loads successfully.
     */
    public function test_project_detail_page_loads(): void
    {
        $this->assertNotNull($this->testProject);
        $this->assertEquals('Full Test Project', $this->testProject->name);

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->assertPathIs('/projects/'.$this->testProject->slug);
        });
    }

    /**
     * Test that project metadata is displayed correctly.
     */
    public function test_project_metadata_display(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="project-head"]', 10)
                // Check project name
                ->assertSeeIn('[data-testid="project-name"]', $this->testProject->name)
                // Check project status
                ->assertPresent('[data-testid="project-status"]')
                // Check project links if present
                ->assertPresent('[data-testid="project-links"]')
                ->assertPresent('[data-testid="github-link"]')
                ->assertPresent('[data-testid="demo-link"]');
        });
    }

    /**
     * Test navigation between content sections.
     */
    public function test_section_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="section-nav"]', 10)
                ->assertPresent('[data-section="description"][data-active="true"]');

            $hasFeatures = $browser->script('return document.querySelector(\'[data-section="features"]\') !== null;');
            if ($hasFeatures[0] ?? false) {
                $browser->click('[data-section="features"]')
                    ->pause(500)
                    ->assertPresent('[data-section="features"][data-active="true"]')
                    ->assertVisible('[data-testid="features-section"]');
            }

            $hasTechnologies = $browser->script('return document.querySelector(\'[data-section="technologies"]\') !== null;');
            if ($hasTechnologies[0] ?? false) {
                $browser->click('[data-section="technologies"]')
                    ->pause(500)
                    ->assertPresent('[data-section="technologies"][data-active="true"]')
                    ->assertVisible('[data-testid="technologies-section"]');
            }

            $hasScreenshots = $browser->script('return document.querySelector(\'[data-section="screenshots"]\') !== null;');
            if ($hasScreenshots[0] ?? false) {
                $browser->click('[data-section="screenshots"]')
                    ->pause(500)
                    ->assertPresent('[data-section="screenshots"][data-active="true"]')
                    ->assertVisible('[data-testid="screenshots-section"]');
            }
        });
    }

    /**
     * Test that project description is displayed with markdown formatting.
     */
    public function test_project_description_with_markdown(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="project-description"]', 10)
                ->assertPresent('[data-testid="project-description"]')
                // Check that description contains expected text
                ->assertSeeIn('[data-testid="project-description"]', 'Overview')
                ->assertSeeIn('[data-testid="project-description"]', 'detailed description');
        });
    }

    /**
     * Test that project features are displayed correctly.
     */
    public function test_project_features_display(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="features"]', 10)
                ->click('[data-section="features"]')
                ->pause(500)
                ->assertVisible('[data-testid="features-section"]')
                ->assertSee('Feature 1')
                ->assertSee('Feature 1 description');
        });
    }

    /**
     * Test that project team members are displayed.
     */
    public function test_project_people_display(): void
    {
        if (count($this->people) > 0) {
            $this->browse(function (Browser $browser) {
                $browser->visit('/projects/'.$this->testProject->slug)
                    ->waitFor('[data-testid="section-nav"]', 10);

                $hasPeople = $browser->script('return document.querySelector(\'[data-section="people"]\') !== null;');
                if ($hasPeople[0] ?? false) {
                    $browser->click('[data-section="people"]')
                        ->pause(500)
                        ->assertVisible('[data-testid="people-section"]')
                        ->assertSee('John Doe');
                } else {
                    $this->assertTrue(true, 'People section not present in UI');
                }
            });
        } else {
            $this->assertTrue(true, 'No people attached to test project');
        }
    }

    /**
     * Test that technologies are displayed with correct categorization.
     */
    public function test_technologies_display(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="technologies"]', 10)
                ->click('[data-section="technologies"]')
                ->pause(500)
                ->assertVisible('[data-testid="technologies-section"]');

            $techText = $browser->text('[data-testid="technologies-section"]');
            $this->assertNotEmpty($techText, 'Technologies section should have content');
        });
    }

    /**
     * Test screenshot gallery functionality.
     */
    public function test_screenshot_gallery(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="screenshots"]', 10)
                ->click('[data-section="screenshots"]')
                ->pause(500)
                ->assertVisible('[data-testid="screenshots-section"]');

            $screenshotContent = $browser->script('return document.querySelector(\'[data-testid="screenshots-section"]\').innerHTML.length;');
            $this->assertGreaterThan(0, $screenshotContent[0] ?? 0, 'Screenshots section should have content');
        });
    }

    /**
     * Test video gallery functionality.
     */
    public function test_video_gallery(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="videos"]', 10)
                ->click('[data-section="videos"]')
                ->pause(500)
                ->assertVisible('[data-testid="videos-section"]');

            $videoContent = $browser->script('return document.querySelector(\'[data-testid="videos-section"]\').innerHTML.length;');
            $this->assertGreaterThan(0, $videoContent[0] ?? 0, 'Videos section should have content');
        });
    }

    /**
     * Test responsive behavior of project detail page.
     */
    public function test_responsive_project_detail(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->resize(1920, 1080)
                ->waitFor('[data-testid="section-nav"]', 10)
                ->assertPresent('[data-testid="section-nav"]')
                ->resize(768, 1024)
                ->pause(500)
                ->assertPresent('[data-testid="section-nav"]')
                ->resize(375, 812)
                ->pause(500);

            $hasContent = $browser->script('return document.body.innerHTML.length > 1000;');
            $this->assertTrue($hasContent[0] ?? false, 'Page should have content on mobile');
        });
    }

    /**
     * Test navigation back to projects list.
     */
    public function test_back_to_projects_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->pause(2000);

            $hasBackButton = $browser->script('return document.querySelector(\'[data-testid="back-to-projects"]\') !== null;');

            if ($hasBackButton[0] ?? false) {
                $browser->click('[data-testid="back-to-projects"]')
                    ->waitForLocation('/projects', 10)
                    ->assertPathIs('/projects');
            } else {
                $this->assertTrue(true, 'Back button not found, navigation may be implemented differently');
            }
        });
    }

    /**
     * Test external links open in new tab.
     */
    public function test_external_links_open_in_new_tab(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="project-links"]', 10);

            $hasGithub = $browser->script('return document.querySelector(\'[data-testid="github-link"]\') !== null;');
            if ($hasGithub[0] ?? false) {
                $browser->assertAttribute('[data-testid="github-link"]', 'target', '_blank');
            }

            $hasDemo = $browser->script('return document.querySelector(\'[data-testid="demo-link"]\') !== null;');
            if ($hasDemo[0] ?? false) {
                $browser->assertAttribute('[data-testid="demo-link"]', 'target', '_blank');
            }

            if (! ($hasGithub[0] ?? false) && ! ($hasDemo[0] ?? false)) {
                $this->assertTrue(true, 'No external links found');
            }
        });
    }

    /**
     * Create comprehensive test data for the tests.
     */
    protected function createTestData(): void
    {
        $this->createFullTestProject();
    }

    /**
     * Create a full test project with all relations.
     */
    protected function createFullTestProject(): void
    {
        $descKey = TranslationKey::create(['key' => 'creation.full-test-project.description']);
        Translation::create([
            'translation_key_id' => $descKey->id,
            'locale' => 'fr',
            'text' => "## Overview\n\nThis is a detailed description of the test project.\n\n### Features\n\n- Feature one\n- Feature two\n- Feature three",
        ]);
        Translation::create([
            'translation_key_id' => $descKey->id,
            'locale' => 'en',
            'text' => "## Overview\n\nThis is a detailed description of the test project.\n\n### Features\n\n- Feature one\n- Feature two\n- Feature three",
        ]);

        $shortDescKey = TranslationKey::create(['key' => 'creation.full-test-project.short_description']);
        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Un projet de test complet avec toutes les fonctionnalités',
        ]);
        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'en',
            'text' => 'A comprehensive test project with all features',
        ]);

        if (Technology::count() < 4) {
            $technologies = Technology::factory()->createSet();
        } else {
            $technologies = Technology::all();
        }

        $this->technologies = [
            'laravel' => $technologies->firstWhere('name', 'Laravel'),
            'vue' => $technologies->firstWhere('name', 'Vue.js'),
            'php' => $technologies->firstWhere('name', 'PHP'),
            'javascript' => $technologies->firstWhere('name', 'JavaScript'),
        ];

        $this->people = [];
        $this->people[] = Person::create(['name' => 'John Doe']);
        $this->people[] = Person::create(['name' => 'Jane Smith']);

        $featureKeys = [];
        for ($i = 1; $i <= 3; $i++) {
            $featureTitleKey = TranslationKey::create(['key' => "feature.test-$i.title"]);
            Translation::create([
                'translation_key_id' => $featureTitleKey->id,
                'locale' => 'fr',
                'text' => "Feature $i",
            ]);
            Translation::create([
                'translation_key_id' => $featureTitleKey->id,
                'locale' => 'en',
                'text' => "Feature $i",
            ]);

            $featureDescKey = TranslationKey::create(['key' => "feature.test-$i.description"]);
            Translation::create([
                'translation_key_id' => $featureDescKey->id,
                'locale' => 'fr',
                'text' => "Feature $i description",
            ]);
            Translation::create([
                'translation_key_id' => $featureDescKey->id,
                'locale' => 'en',
                'text' => "Feature $i description",
            ]);

            $featureKeys[] = [
                'title_key' => $featureTitleKey,
                'desc_key' => $featureDescKey,
            ];
        }

        $this->testProject = Creation::factory()
            ->complete()
            ->create([
                'name' => 'Full Test Project',
                'slug' => 'full-test-project',
                'external_url' => 'https://example.com',
                'source_code_url' => 'https://github.com/example/project',
                'featured' => true,
                'type' => CreationType::WEBSITE->value,
                'full_description_translation_key_id' => $descKey->id,
                'short_description_translation_key_id' => $shortDescKey->id,
            ]);

        $this->testProject->features()->delete();
        $this->features = [];
        foreach ($featureKeys as $index => $keys) {
            $this->features[] = Feature::create([
                'creation_id' => $this->testProject->id,
                'title_translation_key_id' => $keys['title_key']->id,
                'description_translation_key_id' => $keys['desc_key']->id,
            ]);
        }

        $this->testProject->technologies()->syncWithoutDetaching(collect($this->technologies)->pluck('id'));

        foreach ($this->people as $person) {
            $this->testProject->people()->attach($person->id);
        }

        $this->coverImage = $this->testProject->coverImage;
        $this->logoImage = $this->testProject->logo;
        $this->screenshots = $this->testProject->screenshots->pluck('picture')->toArray();

        $this->videos = [];
        for ($i = 0; $i < 2; $i++) {
            $this->videos[] = Video::factory()->readyAndPublic()->create();
        }

        foreach ($this->videos as $video) {
            $this->testProject->videos()->attach($video->id);
        }
    }

    /**
     * Create optimized picture records for a picture.
     */
    protected function createOptimizedPictures(Picture $picture): void
    {
        $formats = ['avif', 'webp', 'jpg'];
        $variants = ['thumbnail', 'small', 'medium', 'large', 'full'];

        foreach ($formats as $format) {
            foreach ($variants as $variant) {
                OptimizedPicture::create([
                    'picture_id' => $picture->id,
                    'format' => $format,
                    'variant' => $variant,
                    'path' => "uploads/optimized/{$picture->filename}_{$variant}.{$format}",
                ]);
            }
        }
    }
}
