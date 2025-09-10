<?php

namespace App\Console\Commands;

use App\Services\AiProviders\AiProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AiProvidersCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:providers:check 
                            {--provider= : Check specific provider only}
                            {--clear-cache : Clear health check cache before testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the availability and health of configured AI providers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            $this->info('Clearing health check cache...');
            foreach (AiProviderFactory::getAvailableProviders() as $providerName) {
                Cache::forget("provider_health_{$providerName}");
            }
        }

        $specificProvider = $this->option('provider');
        $providers = $specificProvider 
            ? [$specificProvider] 
            : AiProviderFactory::getAvailableProviders();

        $this->info('🔍 Checking AI Provider Health');
        $this->info('─────────────────────────────');
        $this->newLine();

        $results = [];
        $allHealthy = true;

        foreach ($providers as $providerName) {
            $this->info("Checking {$providerName}...");
            
            try {
                // Check if provider is configured
                if (!AiProviderFactory::isProviderConfigured($providerName)) {
                    $results[] = [
                        'Provider' => ucfirst($providerName),
                        'Status' => '❌ Not Configured',
                        'Model' => 'N/A',
                        'Response Time' => 'N/A',
                        'Notes' => 'Missing API key or configuration',
                    ];
                    $allHealthy = false;
                    continue;
                }

                // Create provider instance
                $provider = AiProviderFactory::create($providerName);
                
                // Measure response time
                $startTime = microtime(true);
                $isAvailable = $provider->isAvailable();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                if ($isAvailable) {
                    $results[] = [
                        'Provider' => ucfirst($providerName),
                        'Status' => '✅ Available',
                        'Model' => $provider->getModel(),
                        'Response Time' => "{$responseTime}ms",
                        'Notes' => 'Provider is healthy',
                    ];
                } else {
                    $results[] = [
                        'Provider' => ucfirst($providerName),
                        'Status' => '⚠️ Unavailable',
                        'Model' => $provider->getModel(),
                        'Response Time' => "{$responseTime}ms",
                        'Notes' => 'Health check failed',
                    ];
                    $allHealthy = false;
                }
                
            } catch (\Exception $e) {
                $results[] = [
                    'Provider' => ucfirst($providerName),
                    'Status' => '❌ Error',
                    'Model' => 'N/A',
                    'Response Time' => 'N/A',
                    'Notes' => substr($e->getMessage(), 0, 50),
                ];
                $allHealthy = false;
            }
        }

        $this->newLine();
        $this->table(
            ['Provider', 'Status', 'Model', 'Response Time', 'Notes'],
            $results
        );

        // Configuration Summary
        $this->newLine();
        $this->info('⚙️ Configuration Summary');
        $this->info('─────────────────────────');
        
        $fallbackEnabled = config('ai-provider.fallback.enabled');
        $fallbackPriority = config('ai-provider.fallback.priority', []);
        $cacheEnabled = config('ai-provider.cache.enabled');
        $cacheTtl = config('ai-provider.cache.ttl');
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['Primary Provider', config('ai-provider.selected-provider', 'Not set')],
                ['Fallback Enabled', $fallbackEnabled ? 'Yes' : 'No'],
                ['Fallback Priority', implode(' → ', $fallbackPriority)],
                ['Cache Enabled', $cacheEnabled ? 'Yes' : 'No'],
                ['Cache TTL', $cacheEnabled ? ($cacheTtl / 86400) . ' days' : 'N/A'],
                ['Health Check TTL', config('ai-provider.fallback.health_check_ttl', 300) . ' seconds'],
            ]
        );

        // Test with sample prompt if all providers are healthy
        if ($allHealthy && $this->confirm('All providers are healthy. Would you like to test with a sample prompt?')) {
            $this->testProviders($providers);
        }

        return $allHealthy ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Test providers with a sample prompt
     *
     * @param array<string> $providers
     * @return void
     */
    private function testProviders(array $providers): void
    {
        $this->newLine();
        $this->info('🧪 Testing Providers with Sample Prompt');
        $this->info('────────────────────────────────────');
        $this->newLine();

        $systemRole = 'You are a helpful assistant. Respond with a JSON object containing a single "message" field.';
        $prompt = 'Say "Hello from AI!" in JSON format.';

        foreach ($providers as $providerName) {
            try {
                if (!AiProviderFactory::isProviderConfigured($providerName)) {
                    continue;
                }

                $this->info("Testing {$providerName}...");
                
                $provider = AiProviderFactory::create($providerName);
                $startTime = microtime(true);
                
                $response = $provider->prompt($systemRole, $prompt);
                
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $this->info("✅ {$providerName} responded in {$responseTime}ms");
                
                if (isset($response['usage'])) {
                    $usage = $response['usage'];
                    $cost = $provider->estimateCost(
                        $usage['prompt_tokens'] ?? 0,
                        $usage['completion_tokens'] ?? 0
                    );
                    
                    $this->info("   Tokens: {$usage['prompt_tokens']} input, {$usage['completion_tokens']} output");
                    $this->info("   Estimated cost: $" . number_format($cost, 6));
                }
                
                if (isset($response['content'])) {
                    $this->info("   Response: " . substr($response['content'], 0, 100));
                }
                
            } catch (\Exception $e) {
                $this->error("❌ {$providerName} test failed: " . $e->getMessage());
            }
            
            $this->newLine();
        }
    }
}