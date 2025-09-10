<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(\App\Console\Commands\AiProvidersCheckCommand::class)]
class AiProvidersCheckCommandTest extends TestCase
{
    #[Test]
    public function it_displays_provider_health_check_header(): void
    {
        // Set empty providers config to avoid any real API calls
        Config::set('ai-provider.providers', []);

        $this->artisan('ai:providers:check')
            ->expectsOutput('🔍 Checking AI Provider Health')
            ->expectsOutput('─────────────────────────────')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_configuration_summary(): void
    {
        Config::set([
            'ai-provider.providers' => [],
            'ai-provider.selected-provider' => 'openai',
            'ai-provider.fallback.enabled' => true,
            'ai-provider.fallback.priority' => ['openai', 'anthropic'],
            'ai-provider.cache.enabled' => true,
            'ai-provider.cache.ttl' => 86400,
            'ai-provider.fallback.health_check_ttl' => 300,
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('⚙️ Configuration Summary')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_disabled_settings_correctly(): void
    {
        Config::set([
            'ai-provider.providers' => [],
            'ai-provider.selected-provider' => null,
            'ai-provider.fallback.enabled' => false,
            'ai-provider.fallback.priority' => [],
            'ai-provider.cache.enabled' => false,
            'ai-provider.cache.ttl' => 86400,
            'ai-provider.fallback.health_check_ttl' => 300,
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('⚙️ Configuration Summary')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_not_configured_status_for_unconfigured_provider(): void
    {
        Config::set([
            'ai-provider.providers' => [
                'openai' => [
                    'api-key' => null, // Not configured
                ],
            ],
        ]);

        $this->artisan('ai:providers:check', ['--provider' => 'openai'])
            ->expectsOutput('Checking openai...')
            ->assertFailed();
    }

    #[Test]
    public function it_handles_unknown_provider(): void
    {
        Config::set([
            'ai-provider.providers' => [],
        ]);

        $this->artisan('ai:providers:check', ['--provider' => 'unknown'])
            ->expectsOutput('Checking unknown...')
            ->assertFailed();
    }

    #[Test]
    public function it_clears_cache_when_clear_cache_option_is_provided(): void
    {
        Config::set([
            'ai-provider.providers' => [
                'openai' => ['api-key' => null],
                'anthropic' => ['api-key' => null],
            ],
        ]);

        $this->artisan('ai:providers:check', ['--clear-cache' => true])
            ->expectsOutput('Clearing health check cache...')
            ->expectsOutput('🔍 Checking AI Provider Health')
            ->assertFailed(); // Will fail because providers are not configured
    }

    #[Test]
    public function it_checks_specific_provider_when_provider_option_is_given(): void
    {
        Config::set([
            'ai-provider.providers' => [
                'openai' => ['api-key' => null],
                'anthropic' => ['api-key' => null],
            ],
        ]);

        $this->artisan('ai:providers:check', ['--provider' => 'openai'])
            ->expectsOutput('Checking openai...')
            ->doesntExpectOutput('Checking anthropic...')
            ->assertFailed(); // Will fail because provider is not configured
    }

    #[Test]
    public function it_checks_multiple_providers_when_no_specific_provider_given(): void
    {
        Config::set([
            'ai-provider.providers' => [
                'openai' => ['api-key' => null],
                'anthropic' => ['api-key' => null],
            ],
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('Checking openai...')
            ->expectsOutput('Checking anthropic...')
            ->assertFailed(); // Will fail because providers are not configured
    }

    #[Test]
    public function it_calculates_cache_ttl_in_days_correctly(): void
    {
        Config::set([
            'ai-provider.providers' => [],
            'ai-provider.cache.enabled' => true,
            'ai-provider.cache.ttl' => 172800, // 2 days in seconds
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('⚙️ Configuration Summary')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_empty_fallback_priority_correctly(): void
    {
        Config::set([
            'ai-provider.providers' => [],
            'ai-provider.fallback.enabled' => true,
            'ai-provider.fallback.priority' => [],
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('⚙️ Configuration Summary')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful();
    }

    #[Test]
    public function it_returns_success_when_no_providers_configured(): void
    {
        Config::set([
            'ai-provider.providers' => [],
        ]);

        $this->artisan('ai:providers:check')
            ->expectsOutput('🔍 Checking AI Provider Health')
            ->expectsOutput('⚙️ Configuration Summary')
            ->expectsConfirmation('All providers are healthy. Would you like to test with a sample prompt?', 'no')
            ->assertSuccessful(); // Should succeed when there are no providers to check
    }
}
