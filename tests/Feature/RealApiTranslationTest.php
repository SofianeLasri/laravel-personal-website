<?php

namespace Tests\Feature;

use App\Services\AiProviderService;
use Exception;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class RealApiTranslationTest extends TestCase
{
    private string $sampleText;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the sample translation text
        $this->sampleText = File::get(base_path('tests/TranslationSample.md'));
    }

    #[Group('real-api')]
    #[Group('openai')]
    public function test_openai_translation_with_long_text(): void
    {
        // Skip if no OpenAI API key is configured
        if (empty(config('ai-provider.providers.openai.api-key'))) {
            $this->markTestSkipped('OpenAI API key not configured');
        }

        // Force OpenAI provider
        config(['ai-provider.selected-provider' => 'openai']);

        $aiService = app(AiProviderService::class);

        $systemPrompt = 'You are a helpful assistant that translates french markdown text to english and outputs JSON in the format {message:string}. Markdown formatting must be preserved.';
        $userPrompt = "Translate this French text to English:\n\n" . $this->sampleText;

        // Log the request details
        $this->logTestDetails('OpenAI', strlen($this->sampleText), strlen($userPrompt));

        try {
            $startTime = microtime(true);
            $response = $aiService->prompt($systemPrompt, $userPrompt);
            $endTime = microtime(true);

            $duration = round($endTime - $startTime, 2);

            // Assert response structure
            $this->assertIsArray($response);
            $this->assertArrayHasKey('message', $response);
            $this->assertIsString($response['message']);

            // Check that translation actually happened (should contain English text)
            $this->assertStringContainsString('project', strtolower($response['message']));
            $this->assertStringNotContainsString('projet', strtolower($response['message'])); // French word should not be present

            // Log success
            echo "\n✅ OpenAI Translation Success:\n";
            echo "- Original length: " . strlen($this->sampleText) . " chars\n";
            echo "- Translation length: " . strlen($response['message']) . " chars\n";
            echo "- Response time: {$duration}s\n";
            echo "- First 200 chars: " . substr($response['message'], 0, 200) . "...\n";

            // Save the translation for inspection
            File::put(base_path('tests/output_openai_translation.md'), $response['message']);
            echo "- Full translation saved to: tests/output_openai_translation.md\n";

        } catch (Exception $e) {
            echo "\n❌ OpenAI Translation Failed:\n";
            echo "- Error: " . $e->getMessage() . "\n";
            echo "- Error class: " . get_class($e) . "\n";

            // Save error details
            File::put(base_path('tests/output_openai_error.txt'),
                "Error: " . $e->getMessage() . "\n\n" .
                "Stack trace:\n" . $e->getTraceAsString()
            );

            throw $e;
        }
    }

    #[Group('real-api')]
    #[Group('anthropic')]
    public function test_anthropic_translation_with_long_text(): void
    {
        // Skip if no Anthropic API key is configured
        if (empty(config('ai-provider.providers.anthropic.api-key'))) {
            $this->markTestSkipped('Anthropic API key not configured');
        }

        // Force Anthropic provider
        config(['ai-provider.selected-provider' => 'anthropic']);

        $aiService = app(AiProviderService::class);

        $systemPrompt = 'You are a helpful assistant that translates french markdown text to english and outputs JSON in the format {message:string}. Markdown formatting must be preserved.';
        $userPrompt = "Translate this French text to English:\n\n" . $this->sampleText;

        // Log the request details
        $this->logTestDetails('Anthropic', strlen($this->sampleText), strlen($userPrompt));

        try {
            $startTime = microtime(true);
            $response = $aiService->prompt($systemPrompt, $userPrompt);
            $endTime = microtime(true);

            $duration = round($endTime - $startTime, 2);

            // Assert response structure
            $this->assertIsArray($response);
            $this->assertArrayHasKey('message', $response);
            $this->assertIsString($response['message']);

            // Check that translation actually happened (should contain English text)
            $this->assertStringContainsString('project', strtolower($response['message']));
            $this->assertStringNotContainsString('projet', strtolower($response['message'])); // French word should not be present

            // Log success
            echo "\n✅ Anthropic Translation Success:\n";
            echo "- Original length: " . strlen($this->sampleText) . " chars\n";
            echo "- Translation length: " . strlen($response['message']) . " chars\n";
            echo "- Response time: {$duration}s\n";
            echo "- First 200 chars: " . substr($response['message'], 0, 200) . "...\n";

            // Save the translation for inspection
            File::put(base_path('tests/output_anthropic_translation.md'), $response['message']);
            echo "- Full translation saved to: tests/output_anthropic_translation.md\n";

        } catch (Exception $e) {
            echo "\n❌ Anthropic Translation Failed:\n";
            echo "- Error: " . $e->getMessage() . "\n";
            echo "- Error class: " . get_class($e) . "\n";

            // Save error details
            File::put(base_path('tests/output_anthropic_error.txt'),
                "Error: " . $e->getMessage() . "\n\n" .
                "Stack trace:\n" . $e->getTraceAsString()
            );

            throw $e;
        }
    }

    /**
     * Helper to log test details
     */
    private function logTestDetails(string $provider, int $originalLength, int $promptLength): void
    {
        echo "\n🔬 Testing {$provider} Translation:\n";
        echo "- Original text length: {$originalLength} chars\n";
        echo "- Full prompt length: {$promptLength} chars\n";
        echo "- Provider config:\n";
        echo "  - URL: " . config("ai-provider.providers." . strtolower($provider) . ".url") . "\n";
        echo "  - Model: " . config("ai-provider.providers." . strtolower($provider) . ".model") . "\n";
        echo "  - Max tokens: " . config("ai-provider.providers." . strtolower($provider) . ".max-tokens") . "\n";
    }
}