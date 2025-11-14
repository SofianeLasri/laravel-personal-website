<?php

namespace Tests\Browser\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

class CreationDraftEditTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
    }

    #[Test]
    public function it_can_save_creation_draft_with_basic_info(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit')
                ->waitForText('Créer ou modifier une création')
                ->pause(1000) // Wait for form to fully load

                // Fill in basic information
                ->type('input[name="name"]', 'Test Creation Draft')
                ->type('input[name="slug"]', 'test-creation-draft')

                // Select type
                ->click('[data-testid="creation-type-selector"]')
                ->pause(500)
                ->click('[data-testid="type-website"]')
                ->pause(500)

                ->value('input[name="started_at"]', '2025-01-01')
                ->click('input[name="slug"]') // Trigger blur event on date field
                ->pause(500)

                // Fill in short description (Textarea with placeholder "Courte description")
                ->type('textarea[placeholder="Courte description"]', 'This is a short description for testing')
                ->pause(1000)

                // Submit the form
                ->screenshot('before-submit')
                ->press('Créer')
                ->waitForText('Publier', 10) // Wait for "Publier" button which only appears after draft is created
                ->pause(1000)
                ->screenshot('after-submit')
                ->assertPathIs('/dashboard/creations/edit');
        });
    }

    #[Test]
    public function it_validates_required_fields_on_save(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit')
                ->waitForText('Créer ou modifier une création')
                ->pause(1000)

                // Try to submit without filling required fields
                ->press('Créer')
                ->pause(2000)
                ->assertSee('Le nom est requis');
        });
    }

    #[Test]
    public function it_can_add_short_description_to_draft(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit')
                ->waitForText('Créer ou modifier une création')
                ->pause(1000)

                // Fill in required fields
                ->type('input[name="name"]', 'Test Creation with Description')
                ->type('input[name="slug"]', 'test-creation-with-description')

                // Select type
                ->click('[data-testid="creation-type-selector"]')
                ->pause(500)
                ->click('[data-testid="type-library"]')
                ->pause(500)

                ->value('input[name="started_at"]', '2025-02-01')
                ->click('input[name="slug"]') // Trigger blur event on date field
                ->pause(500)

                // Add short description
                ->type('textarea[placeholder="Courte description"]', 'This is a comprehensive short description')
                ->pause(1000)

                // Submit
                ->press('Créer')
                ->waitForText('Publier', 5) // Wait for "Publier" button which only appears after draft is created
                ->pause(1000)
                ->assertPathIs('/dashboard/creations/edit')

                // Reload and check that description persists
                ->refresh()
                ->waitForText('Créer ou modifier une création')
                ->pause(2000)
                ->assertInputValue('textarea[placeholder="Courte description"]', 'This is a comprehensive short description');
        });
    }

    #[Test]
    public function it_can_add_content_blocks_to_draft(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit')
                ->waitForText('Créer ou modifier une création')
                ->pause(1000)

                // Fill in required fields first
                ->type('input[name="name"]', 'Creation with Content Blocks')
                ->type('input[name="slug"]', 'creation-with-content-blocks')

                // Select type
                ->click('[data-testid="creation-type-selector"]')
                ->pause(500)
                ->click('[data-testid="type-portfolio"]')
                ->pause(500)

                ->value('input[name="started_at"]', '2025-03-01')
                ->click('input[name="slug"]') // Trigger blur event on date field
                ->pause(500)
                ->type('textarea[placeholder="Courte description"]', 'Short desc for content blocks test')
                ->pause(1000)

                // Save first to create the draft
                ->press('Créer')
                ->waitForText('Publier', 5) // Wait for "Publier" button which only appears after draft is created
                ->pause(1000)
                ->assertPathIs('/dashboard/creations/edit')

                // Now add content blocks
                ->waitFor('[data-testid="add-text-button"]', 5) // Wait for ContentBuilder to render
                ->click('[data-testid="add-text-button"]')
                ->pause(2000) // Wait for content block to be added
                ->assertSee('Texte (Markdown)')

                // Wait for autosave (the component saves after 1.5 seconds of inactivity)
                ->pause(3000)

                // Reload and verify content block persists
                ->refresh()
                ->waitForText('Créer ou modifier une création')
                ->pause(2000)
                ->assertSee('Texte (Markdown)');
        });
    }
}
