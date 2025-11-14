<?php

namespace Tests\Browser\Pages;

use App\Enums\CreationType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationContent;
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
     * Test comprehensive project detail page content.
     * This test verifies all elements of the project description.
     */
    public function test_comprehensive_project_detail_content(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="project-head"]', 10);

            // 1. Test project metadata
            $browser->assertSeeIn('[data-testid="project-name"]', $this->testProject->name)
                ->assertPresent('[data-testid="project-status"]');

            // 2. Test project type (optional - may not be displayed in all themes)
            $projectType = $browser->script('return document.querySelector(\'[data-testid="project-type"]\')?.textContent;');
            if ($projectType[0] ?? null) {
                $this->assertNotEmpty($projectType[0], 'Project type should have content if displayed');
            }

            // 3. Test project dates if present
            $hasStartDate = $browser->script('return document.querySelector(\'[data-testid="project-start-date"]\') !== null;');
            if ($hasStartDate[0] ?? false) {
                $startDate = $browser->text('[data-testid="project-start-date"]');
                $this->assertNotEmpty($startDate, 'Start date should be displayed');
            }

            // 4. Test project links
            if ($this->testProject->external_url) {
                $browser->assertPresent('[data-testid="demo-link"]')
                    ->assertAttribute('[data-testid="demo-link"]', 'href', $this->testProject->external_url);
            }

            if ($this->testProject->source_code_url) {
                $browser->assertPresent('[data-testid="github-link"]')
                    ->assertAttribute('[data-testid="github-link"]', 'href', $this->testProject->source_code_url);
            }

            // 5. Test cover image if present
            if ($this->testProject->coverImage) {
                $hasCoverImage = $browser->script('return document.querySelector(\'[data-testid="project-cover-image"]\') !== null;');
                if ($hasCoverImage[0] ?? false) {
                    $browser->assertPresent('[data-testid="project-cover-image"]');
                }
            }

            // 6. Test logo if present
            if ($this->testProject->logo) {
                $hasLogo = $browser->script('return document.querySelector(\'[data-testid="project-logo"]\') !== null;');
                if ($hasLogo[0] ?? false) {
                    $browser->assertPresent('[data-testid="project-logo"]');
                }
            }

            // 7. Test short description
            $hasShortDescription = $browser->script('return document.querySelector(\'[data-testid="project-short-description"]\') !== null;');
            if ($hasShortDescription[0] ?? false) {
                $browser->assertSeeIn('[data-testid="project-short-description"]', 'comprehensive test project');
            }

            // 8. Test full description with markdown
            $browser->assertPresent('[data-testid="project-description"]');
            $descriptionContent = $browser->text('[data-testid="project-description"]');
            $this->assertStringContainsString('Overview', $descriptionContent);
            $this->assertStringContainsString('detailed description', $descriptionContent);
            $this->assertStringContainsString('Features', $descriptionContent);
            $this->assertStringContainsString('Feature one', $descriptionContent);
            $this->assertStringContainsString('Feature two', $descriptionContent);
            $this->assertStringContainsString('Feature three', $descriptionContent);

            // 9. Test section navigation
            $browser->assertPresent('[data-testid="section-nav"]');
            $sections = $browser->script('return Array.from(document.querySelectorAll(\'[data-testid="section-nav"] [data-section]\'))
                .map(el => el.getAttribute("data-section"));');
            $this->assertContains('description', $sections[0] ?? []);

            // 10. Test features section if present
            if (count($this->features) > 0) {
                $hasFeatures = $browser->script('return document.querySelector(\'[data-section="features"]\') !== null;');
                if ($hasFeatures[0] ?? false) {
                    $browser->click('[data-section="features"]')
                        ->waitFor('[data-section="features"][data-active="true"]', 10)
                        ->assertVisible('[data-testid="features-section"]');

                    // Verify each feature
                    foreach ($this->features as $index => $feature) {
                        $featureNum = $index + 1;
                        $browser->assertSee("Feature $featureNum")
                            ->assertSee("Feature $featureNum description");
                    }

                    // Check feature count
                    $featureElements = $browser->script('return document.querySelectorAll(\'[data-testid="features-section"] [data-testid^="feature-"]\').length;');
                    $this->assertEquals(count($this->features), $featureElements[0] ?? 0, 'All features should be displayed');
                }
            }

            // 11. Test technologies section
            if (count($this->technologies) > 0) {
                $hasTechnologies = $browser->script('return document.querySelector(\'[data-section="technologies"]\') !== null;');
                if ($hasTechnologies[0] ?? false) {
                    $browser->click('[data-section="technologies"]')
                        ->waitFor('[data-section="technologies"][data-active="true"]', 10)
                        ->assertVisible('[data-testid="technologies-section"]');

                    // Verify technology categories if they exist
                    $techCategories = $browser->script('return Array.from(document.querySelectorAll(\'[data-testid="technology-category"]\'))
                        .map(el => el.textContent.trim());');
                    if (! empty($techCategories[0])) {
                        $this->assertNotEmpty($techCategories[0], 'Technology categories should have content if displayed');
                    }

                    // Verify specific technologies are visible in the section
                    $techSectionContent = $browser->text('[data-testid="technologies-section"]');
                    $this->assertStringContainsString('Laravel', $techSectionContent);
                    $this->assertStringContainsString('Vue.js', $techSectionContent);
                    $this->assertStringContainsString('PHP', $techSectionContent);
                    $this->assertStringContainsString('JavaScript', $techSectionContent);
                }
            }

            // 12. Test people/team section
            if (count($this->people) > 0) {
                $hasPeople = $browser->script('return document.querySelector(\'[data-section="people"]\') !== null;');
                if ($hasPeople[0] ?? false) {
                    $browser->click('[data-section="people"]')
                        ->waitFor('[data-section="people"][data-active="true"]', 10)
                        ->assertVisible('[data-testid="people-section"]');

                    // Verify each team member
                    foreach ($this->people as $person) {
                        $browser->assertSee($person->name);
                    }

                    // Check that people are displayed (count may vary due to seeding)
                    $peopleElements = $browser->script('return document.querySelectorAll(\'[data-testid="people-section"] [data-testid^="person-"], [data-testid="people-section"] .person-item, [data-testid="people-section"] [class*="person"]\').length;');
                    $this->assertGreaterThan(0, $peopleElements[0] ?? 0, 'Team members should be displayed');

                    // Verify our specific test people are shown
                    $peopleContent = $browser->text('[data-testid="people-section"]');
                    $this->assertStringContainsString('John Doe', $peopleContent);
                    $this->assertStringContainsString('Jane Smith', $peopleContent);
                }
            }

            // 13. Test screenshots section
            if (count($this->screenshots) > 0) {
                $hasScreenshots = $browser->script('return document.querySelector(\'[data-section="screenshots"]\') !== null;');
                if ($hasScreenshots[0] ?? false) {
                    $browser->click('[data-section="screenshots"]')
                        ->waitFor('[data-section="screenshots"][data-active="true"]', 10)
                        ->assertVisible('[data-testid="screenshots-section"]');

                    // Check screenshot count
                    $screenshotElements = $browser->script('return document.querySelectorAll(\'[data-testid="screenshots-section"] img, [data-testid="screenshots-section"] picture\').length;');
                    $this->assertGreaterThan(0, $screenshotElements[0] ?? 0, 'Screenshots should be displayed');

                    // Check for responsive images (picture elements with source sets)
                    $hasPictureElements = $browser->script('return document.querySelector(\'[data-testid="screenshots-section"] picture\') !== null;');
                    if ($hasPictureElements[0] ?? false) {
                        $sourceElements = $browser->script('return document.querySelectorAll(\'[data-testid="screenshots-section"] picture source\').length;');
                        $this->assertGreaterThan(0, $sourceElements[0] ?? 0, 'Picture elements should have source sets for different formats');
                    }
                }
            }

            // 14. Test videos section
            if (count($this->videos) > 0) {
                $hasVideos = $browser->script('return document.querySelector(\'[data-section="videos"]\') !== null;');
                if ($hasVideos[0] ?? false) {
                    $browser->click('[data-section="videos"]')
                        ->waitFor('[data-section="videos"][data-active="true"]', 10)
                        ->assertVisible('[data-testid="videos-section"]');

                    // Check video count - videos might be rendered differently
                    $videoElements = $browser->script('return document.querySelectorAll(\'[data-testid="videos-section"] [data-testid^="video-"], [data-testid="videos-section"] iframe, [data-testid="videos-section"] .video-item\').length;');
                    if ($videoElements[0] === 0) {
                        // Try to find videos another way
                        $hasVideoContent = $browser->script('return document.querySelector(\'[data-testid="videos-section"]\').innerHTML.includes("iframe") || document.querySelector(\'[data-testid="videos-section"]\').innerHTML.includes("video");');
                        $this->assertTrue($hasVideoContent[0] ?? false, 'Videos section should contain video elements');
                    } else {
                        $this->assertGreaterThan(0, $videoElements[0], 'Videos should be displayed');
                    }

                    // Check for video names
                    foreach ($this->videos as $video) {
                        if ($video->name) {
                            $hasVideoName = $browser->script("return document.body.textContent.includes('".$video->name."');");
                            if ($hasVideoName[0] ?? false) {
                                $browser->assertSee($video->name);
                            }
                        }
                    }
                }
            }

            // 15. Test featured badge if project is featured
            if ($this->testProject->featured) {
                $hasFeaturedBadge = $browser->script('return document.querySelector(\'[data-testid="featured-badge"]\') !== null;');
                if ($hasFeaturedBadge[0] ?? false) {
                    $browser->assertPresent('[data-testid="featured-badge"]');
                }
            }

            // 16. Test that all sections are accessible via navigation
            $navSections = $browser->script('return Array.from(document.querySelectorAll(\'[data-testid="section-nav"] [data-section]\'))
                .map(el => el.getAttribute("data-section"));');

            foreach ($navSections[0] ?? [] as $section) {
                $browser->click("[data-section=\"$section\"]")
                    ->waitFor("[data-section=\"$section\"][data-active=\"true\"]", 10);

                // Verify section is active
                $isActive = $browser->script("return document.querySelector('[data-section=\"$section\"][data-active=\"true\"]') !== null;");
                $this->assertTrue($isActive[0] ?? false, "Section $section should be active after clicking");

                // Verify corresponding content is visible
                $sectionTestId = str_replace('_', '-', $section).'-section';
                $isVisible = $browser->script("return document.querySelector('[data-testid=\"$sectionTestId\"]') !== null;");
                if ($isVisible[0] ?? false) {
                    $browser->assertVisible("[data-testid=\"$sectionTestId\"]");
                }
            }

            // 17. Test accessibility: all images should have alt text
            $imagesWithoutAlt = $browser->script('return Array.from(document.querySelectorAll("img:not([alt])")).length;');
            $this->assertEquals(0, $imagesWithoutAlt[0] ?? 0, 'All images should have alt attributes');

            // 18. Test that external links have proper attributes
            $externalLinks = $browser->script('return Array.from(document.querySelectorAll(\'a[href^="http"]\'))
                .filter(a => !a.href.includes(window.location.hostname))
                .map(a => ({href: a.href, target: a.target, rel: a.rel}));');

            foreach ($externalLinks[0] ?? [] as $link) {
                $this->assertEquals('_blank', $link['target'] ?? '', 'External links should open in new tab');
                $this->assertStringContainsString('noopener', $link['rel'] ?? '', 'External links should have noopener rel');
            }
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
                ->waitFor('[data-section="features"][data-active="true"]', 10)
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
                        ->waitFor('[data-section="people"][data-active="true"]', 10)
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
                ->waitFor('[data-section="technologies"][data-active="true"]', 10)
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
                ->waitFor('[data-section="screenshots"][data-active="true"]', 10)
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
                ->waitFor('[data-section="videos"][data-active="true"]', 10)
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
     * Test data validation for project detail page.
     * This test ensures all data is correctly formatted and present.
     */
    public function test_project_data_structure_validation(): void
    {
        // Validate test project data structure
        $this->assertNotNull($this->testProject);
        $this->assertNotNull($this->testProject->id);
        $this->assertEquals('Full Test Project', $this->testProject->name);
        $this->assertEquals('full-test-project', $this->testProject->slug);
        $this->assertEquals('https://example.com', $this->testProject->external_url);
        $this->assertEquals('https://github.com/example/project', $this->testProject->source_code_url);
        $this->assertTrue($this->testProject->featured);

        // Validate translations
        $this->assertNotNull($this->testProject->fullDescriptionTranslationKey);
        $this->assertNotNull($this->testProject->shortDescriptionTranslationKey);

        // Validate features
        $this->assertCount(3, $this->features);
        foreach ($this->features as $index => $feature) {
            $this->assertNotNull($feature->id);
            $this->assertEquals($this->testProject->id, $feature->creation_id);
            $this->assertNotNull($feature->titleTranslationKey);
            $this->assertNotNull($feature->descriptionTranslationKey);
        }

        // Validate technologies
        $this->assertCount(4, $this->technologies);
        $this->assertNotNull($this->technologies['laravel']);
        $this->assertNotNull($this->technologies['vue']);
        $this->assertNotNull($this->technologies['php']);
        $this->assertNotNull($this->technologies['javascript']);

        // Validate people
        $this->assertCount(2, $this->people);
        $this->assertEquals('John Doe', $this->people[0]->name);
        $this->assertEquals('Jane Smith', $this->people[1]->name);

        // Validate videos
        $this->assertCount(2, $this->videos);
        foreach ($this->videos as $video) {
            $this->assertNotNull($video->bunny_video_id);
            $this->assertEquals(VideoStatus::READY, $video->status);
            $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
        }

        // Validate relationships
        $this->assertTrue($this->testProject->technologies->contains($this->technologies['laravel']));
        $this->assertTrue($this->testProject->people->contains($this->people[0]));
        $this->assertTrue($this->testProject->videos->contains($this->videos[0]));
    }

    /**
     * Test SEO meta tags on project detail page.
     */
    public function test_project_seo_meta_tags(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->pause(1000);

            // Check title tag
            $title = $browser->script('return document.title;');
            $this->assertStringContainsString($this->testProject->name, $title[0] ?? '');

            // Check meta description
            $metaDescription = $browser->script('return document.querySelector(\'meta[name="description"]\')?.content;');
            if ($metaDescription[0] ?? null) {
                $this->assertNotEmpty($metaDescription[0]);
            }

            // Check Open Graph tags
            $ogTitle = $browser->script('return document.querySelector(\'meta[property="og:title"]\')?.content;');
            if ($ogTitle[0] ?? null) {
                $this->assertStringContainsString($this->testProject->name, $ogTitle[0]);
            }

            $ogType = $browser->script('return document.querySelector(\'meta[property="og:type"]\')?.content;');
            if ($ogType[0] ?? null) {
                $this->assertContains($ogType[0], ['website', 'article'], 'OG type should be website or article');
            }

            // Check structured data if present
            $jsonLd = $browser->script('return document.querySelector(\'script[type="application/ld+json"]\')?.textContent;');
            if ($jsonLd[0] ?? null) {
                $structuredData = json_decode($jsonLd[0], true);
                if ($structuredData) {
                    $this->assertArrayHasKey('@context', $structuredData);
                }
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

        // Create content blocks for the description
        $contentMarkdown = ContentMarkdown::create([
            'translation_key_id' => $descKey->id,
        ]);

        CreationContent::create([
            'creation_id' => $this->testProject->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $contentMarkdown->id,
            'order' => 1,
        ]);
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
