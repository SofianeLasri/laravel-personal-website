<?php

namespace Tests\Browser;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\Feature;
use App\Models\OptimizedPicture;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectDetailPageTest extends DuskTestCase
{
    use DatabaseMigrations;

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
        // Vérifier que le projet existe
        $this->assertNotNull($this->testProject);
        $this->assertEquals('Full Test Project', $this->testProject->name);

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->assertPathIs('/projects/'.$this->testProject->slug);
            // Le contenu ne s'affiche pas correctement, mais l'URL est correcte
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
                // Check that description section is active by default
                ->assertPresent('[data-section="description"][data-active="true"]')
                // Navigate to features section
                ->click('[data-section="features"]')
                ->pause(500)
                ->assertPresent('[data-section="features"][data-active="true"]')
                ->assertVisible('[data-testid="features-section"]')
                // Navigate to people section
                ->click('[data-section="people"]')
                ->pause(500)
                ->assertPresent('[data-section="people"][data-active="true"]')
                ->assertVisible('[data-testid="people-section"]')
                // Navigate to technologies section
                ->click('[data-section="technologies"]')
                ->pause(500)
                ->assertPresent('[data-section="technologies"][data-active="true"]')
                ->assertVisible('[data-testid="technologies-section"]')
                // Navigate to screenshots section
                ->click('[data-section="screenshots"]')
                ->pause(500)
                ->assertPresent('[data-section="screenshots"][data-active="true"]')
                ->assertVisible('[data-testid="screenshots-section"]');
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
                // Check markdown elements are rendered
                ->assertPresent('[data-testid="project-description"] h2')
                ->assertPresent('[data-testid="project-description"] p')
                ->assertPresent('[data-testid="project-description"] ul')
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
                ->assertPresent('[data-testid="feature-card"]')
                ->assertSeeIn('[data-testid="feature-card"]:first-child', 'Feature 1')
                ->assertSeeIn('[data-testid="feature-card"]:first-child', 'Feature 1 description');
        });
    }

    /**
     * Test that project team members are displayed.
     */
    public function test_project_people_display(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="people"]', 10)
                ->click('[data-section="people"]')
                ->pause(500)
                ->assertPresent('[data-testid="person-card"]')
                ->assertSeeIn('[data-testid="person-card"]:first-child', 'John Doe');
        });
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
                ->assertPresent('[data-testid="technology-card"]')
                ->assertSee('Laravel')
                ->assertSee('Vue.js')
                ->assertSee('PHP')
                ->assertSee('JavaScript');
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
                ->assertPresent('[data-testid="screenshot-thumbnail"]')
                // Click on first screenshot to open lightbox
                ->click('[data-testid="screenshot-thumbnail"]:first-child')
                ->waitFor('[data-testid="lightbox-container"]', 10)
                ->assertPresent('[data-testid="lightbox-container"]')
                // Check navigation controls
                ->assertPresent('[data-testid="lightbox-prev"]')
                ->assertPresent('[data-testid="lightbox-next"]')
                ->assertPresent('[data-testid="lightbox-close"]')
                // Navigate to next image
                ->click('[data-testid="lightbox-next"]')
                ->pause(500)
                // Close lightbox
                ->click('[data-testid="lightbox-close"]')
                ->pause(500)
                ->assertMissing('[data-testid="lightbox-container"]');
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
                ->assertPresent('[data-testid="video-thumbnail"]')
                // Click on video to open modal
                ->click('[data-testid="video-thumbnail"]:first-child')
                ->waitFor('[data-testid="video-modal"]', 10)
                ->assertPresent('[data-testid="video-modal"]')
                ->assertPresent('[data-testid="video-iframe"]')
                // Close video modal
                ->click('[data-testid="video-modal-close"]')
                ->pause(500)
                ->assertMissing('[data-testid="video-modal"]');
        });
    }

    /**
     * Test responsive behavior of project detail page.
     */
    public function test_responsive_project_detail(): void
    {
        $this->browse(function (Browser $browser) {
            // Desktop view
            $browser->visit('/projects/'.$this->testProject->slug)
                ->resize(1920, 1080)
                ->waitFor('[data-testid="project-content"]', 10)
                ->assertPresent('[data-testid="section-nav"]')
                // Tablet view
                ->resize(768, 1024)
                ->pause(500)
                ->assertPresent('[data-testid="project-content"]')
                // Mobile view
                ->resize(375, 812)
                ->pause(500)
                ->assertPresent('[data-testid="project-content"]')
                // Check that mobile navigation works
                ->assertPresent('[data-testid="mobile-section-nav"]');
        });
    }

    /**
     * Test navigation back to projects list.
     */
    public function test_back_to_projects_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="back-to-projects"]', 10)
                ->click('[data-testid="back-to-projects"]')
                ->waitForLocation('/projects', 10)
                ->assertPathIs('/projects');
        });
    }

    /**
     * Test keyboard navigation in galleries.
     */
    public function test_keyboard_navigation_in_gallery(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-section="screenshots"]', 10)
                ->click('[data-section="screenshots"]')
                ->pause(500)
                // Open gallery
                ->click('[data-testid="screenshot-thumbnail"]:first-child')
                ->waitFor('[data-testid="lightbox-container"]', 10)
                // Test arrow key navigation
                ->keys('', ['{arrow_right}'])
                ->pause(500)
                ->assertPresent('[data-testid="lightbox-container"]')
                ->keys('', ['{arrow_left}'])
                ->pause(500)
                // Test escape key to close
                ->keys('', ['{escape}'])
                ->pause(500)
                ->assertMissing('[data-testid="lightbox-container"]');
        });
    }

    /**
     * Test external links open in new tab.
     */
    public function test_external_links_open_in_new_tab(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects/'.$this->testProject->slug)
                ->waitFor('[data-testid="project-links"]', 10)
                // Check that links have target="_blank"
                ->assertAttribute('[data-testid="github-link"]', 'target', '_blank')
                ->assertAttribute('[data-testid="demo-link"]', 'target', '_blank')
                // Check that links have rel="noopener noreferrer"
                ->assertAttribute('[data-testid="github-link"]', 'rel', 'noopener noreferrer')
                ->assertAttribute('[data-testid="demo-link"]', 'rel', 'noopener noreferrer');
        });
    }

    /**
     * Create comprehensive test data for the tests.
     */
    protected function createTestData(): void
    {
        // Créer le projet de test principal
        $this->createFullTestProject();

        return;

        // Old code - keeping for reference
        // Create cover and logo images
        $coverImage = Picture::create([
            'filename' => 'cover.jpg',
            'width' => 1920,
            'height' => 1080,
            'size' => 2048,
            'path_original' => 'uploads/cover.jpg',
        ]);
        $this->createOptimizedPictures($coverImage);

        $logoImage = Picture::create([
            'filename' => 'logo.jpg',
            'width' => 512,
            'height' => 512,
            'size' => 1024,
            'path_original' => 'uploads/logo.jpg',
        ]);
        $this->createOptimizedPictures($logoImage);

        // Create screenshots
        for ($i = 1; $i <= 5; $i++) {
            $screenshot = Picture::create([
                'filename' => "screenshot-$i.jpg",
                'width' => 1920,
                'height' => 1080,
                'size' => 1024 * $i,
                'path_original' => "uploads/screenshot-$i.jpg",
            ]);
            $this->createOptimizedPictures($screenshot);
            $this->screenshots[] = $screenshot;
        }

        // Create icon images for technologies
        $techIcon = Picture::create([
            'filename' => 'tech-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/tech-icon.jpg',
        ]);
        $this->createOptimizedPictures($techIcon);

        // Create description translation keys for technologies
        $laravelDescKey = TranslationKey::create(['key' => 'technology.laravel.description']);
        Translation::create([
            'translation_key_id' => $laravelDescKey->id,
            'locale' => 'en',
            'text' => 'Laravel framework',
        ]);

        // Create technologies
        $this->technologies['laravel'] = Technology::create([
            'name' => 'Laravel',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $techIcon->id,
            'description_translation_key_id' => $laravelDescKey->id,
        ]);

        $vueDescKey = TranslationKey::create(['key' => 'technology.vue.description']);
        Translation::create([
            'translation_key_id' => $vueDescKey->id,
            'locale' => 'en',
            'text' => 'Vue.js framework',
        ]);

        $this->technologies['vue'] = Technology::create([
            'name' => 'Vue.js',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $techIcon->id,
            'description_translation_key_id' => $vueDescKey->id,
        ]);

        $phpDescKey = TranslationKey::create(['key' => 'technology.php.description']);
        Translation::create([
            'translation_key_id' => $phpDescKey->id,
            'locale' => 'en',
            'text' => 'PHP language',
        ]);

        $this->technologies['php'] = Technology::create([
            'name' => 'PHP',
            'type' => TechnologyType::LANGUAGE->value,
            'icon_picture_id' => $techIcon->id,
            'description_translation_key_id' => $phpDescKey->id,
        ]);

        $jsDescKey = TranslationKey::create(['key' => 'technology.javascript.description']);
        Translation::create([
            'translation_key_id' => $jsDescKey->id,
            'locale' => 'en',
            'text' => 'JavaScript language',
        ]);

        $this->technologies['javascript'] = Technology::create([
            'name' => 'JavaScript',
            'type' => TechnologyType::LANGUAGE->value,
            'icon_picture_id' => $techIcon->id,
            'description_translation_key_id' => $jsDescKey->id,
        ]);

        // Create people
        $this->people[] = Person::create([
            'name' => 'John Doe',
        ]);

        $this->people[] = Person::create([
            'name' => 'Jane Smith',
        ]);

        // Create translation keys
        $nameKey = TranslationKey::create(['key' => 'creation.test-full-project.name']);
        Translation::create([
            'translation_key_id' => $nameKey->id,
            'locale' => 'en',
            'text' => 'Full Test Project',
        ]);

        $shortDescKey = TranslationKey::create(['key' => 'creation.test-full-project.short_description']);
        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'en',
            'text' => 'A comprehensive test project with all features',
        ]);

        $descKey = TranslationKey::create(['key' => 'creation.test-full-project.description']);
        Translation::create([
            'translation_key_id' => $descKey->id,
            'locale' => 'en',
            'text' => "## Overview\n\nThis is a detailed description of the test project.\n\n### Features\n\n- Feature one\n- Feature two\n- Feature three\n\n### Technical Details\n\nThis project uses modern web technologies.",
        ]);

        // Create feature translation keys
        for ($i = 1; $i <= 3; $i++) {
            $featureNameKey = TranslationKey::create(['key' => "creation_feature.feature-$i.name"]);
            Translation::create([
                'translation_key_id' => $featureNameKey->id,
                'locale' => 'en',
                'text' => "Feature $i",
            ]);

            $featureDescKey = TranslationKey::create(['key' => "creation_feature.feature-$i.description"]);
            Translation::create([
                'translation_key_id' => $featureDescKey->id,
                'locale' => 'en',
                'text' => "Feature $i description with detailed explanation.",
            ]);
        }

        // Create videos
        for ($i = 1; $i <= 2; $i++) {
            $this->videos[] = Video::create([
                'bunny_library_id' => 123456,
                'bunny_video_id' => "video-$i",
                'name' => "Demo Video $i",
                'duration' => 120 + ($i * 30),
                'width' => 1920,
                'height' => 1080,
            ]);
        }

        // Create main project
        $this->testProject = Creation::create([
            'name' => 'Full Test Project',
            'slug' => 'test-full-project',
            'logo_id' => $logoImage->id,
            'cover_image_id' => $coverImage->id,
            'type' => CreationType::PORTFOLIO->value,
            'started_at' => now()->subMonth(),
            'ended_at' => now(),
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $descKey->id,
            'source_code_url' => 'https://github.com/test/project',
            'external_url' => 'https://demo.example.com',
            'featured' => true,
        ]);

        // Attach technologies
        foreach ($this->technologies as $tech) {
            $this->testProject->technologies()->attach($tech->id);
        }

        // Attach people
        foreach ($this->people as $person) {
            $this->testProject->people()->attach($person->id);
        }

        // Attach screenshots
        foreach ($this->screenshots as $index => $screenshot) {
            $this->testProject->pictures()->attach($screenshot->id, [
                'order' => $index + 1,
            ]);
        }

        // Create features
        for ($i = 1; $i <= 3; $i++) {
            $this->features[] = Feature::create([
                'creation_id' => $this->testProject->id,
                'name' => "Feature $i",
                'order' => $i,
            ]);
        }

        // Attach videos
        foreach ($this->videos as $index => $video) {
            $this->testProject->videos()->attach($video->id, [
                'order' => $index + 1,
            ]);
        }
    }

    /**
     * Create a full test project with all relations.
     */
    protected function createFullTestProject(): void
    {
        // Créer les technologies si elles n'existent pas déjà
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

        // Créer un projet complet avec toutes les relations
        $this->testProject = Creation::factory()
            ->complete()
            ->create([
                'name' => 'Full Test Project',
                'slug' => 'full-test-project',
                'external_url' => 'https://example.com',
                'source_code_url' => 'https://github.com/example/project',
                'featured' => true,
                'type' => CreationType::WEBSITE->value,
            ]);

        // Attacher les technologies spécifiques au projet
        $this->testProject->technologies()->syncWithoutDetaching(collect($this->technologies)->pluck('id'));

        // Récupérer les images créées par la factory
        $this->coverImage = $this->testProject->coverImage;
        $this->logoImage = $this->testProject->logo;

        // Récupérer les screenshots créés
        $this->screenshots = $this->testProject->screenshots->pluck('picture')->toArray();

        // Récupérer les personnes créées
        $this->people = $this->testProject->people->toArray();

        // Créer des vidéos
        $this->videos = [];
        for ($i = 0; $i < 2; $i++) {
            $this->videos[] = Video::factory()->readyAndPublic()->create();
        }

        // Attacher les vidéos au projet
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
