<?php

declare(strict_types=1);

namespace Tests\Feature\Services\AI;

use App\Models\Picture;
use App\Services\AI\AiImagePromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(AiImagePromptService::class)]
class AiImagePromptServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiImagePromptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiImagePromptService::class);

        // Set up fake storage
        Storage::fake('public');

        // Configure AI provider for tests
        config([
            'ai-provider.selected-provider' => 'openai',
            'ai-provider.providers.openai' => [
                'url' => 'https://api.openai.com/v1/chat/completions',
                'model' => 'gpt-4-vision-preview',
                'api-key' => 'test-key',
                'max-tokens' => 1000,
            ],
        ]);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_prompts_with_pictures_using_openai(): void
    {
        // Create a test image and store it
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($image);
        $imageContent = ob_get_clean();
        imagedestroy($image);

        $imagePath = 'uploads/test-image.jpg';
        Storage::disk('public')->put($imagePath, $imageContent);

        $picture = Picture::factory()->create([
            'path_original' => $imagePath,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"description": "A test image"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->prompt(
            'You are an image analyzer',
            'Describe this image',
            $picture
        );

        $this->assertIsArray($result);
        $this->assertEquals('A test image', $result['description']);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_prompts_with_multiple_pictures(): void
    {
        // Create test images
        $pictures = [];
        for ($i = 0; $i < 2; $i++) {
            $image = imagecreatetruecolor(100, 100);
            ob_start();
            imagejpeg($image);
            $imageContent = ob_get_clean();
            imagedestroy($image);

            $imagePath = "uploads/test-image-{$i}.jpg";
            Storage::disk('public')->put($imagePath, $imageContent);

            $pictures[] = Picture::factory()->create([
                'path_original' => $imagePath,
            ]);
        }

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"description": "Multiple test images"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->prompt(
            'You are an image analyzer',
            'Compare these images',
            ...$pictures
        );

        $this->assertIsArray($result);
        $this->assertEquals('Multiple test images', $result['description']);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_uses_anthropic_when_configured(): void
    {
        config([
            'ai-provider.selected-provider' => 'anthropic',
            'ai-provider.providers.anthropic' => [
                'url' => 'https://api.anthropic.com/v1/messages',
                'model' => 'claude-3-opus-20240229',
                'api-key' => 'test-key',
                'max-tokens' => 1000,
            ],
        ]);

        // Create test image
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($image);
        $imageContent = ob_get_clean();
        imagedestroy($image);

        $imagePath = 'uploads/test-anthropic.jpg';
        Storage::disk('public')->put($imagePath, $imageContent);

        $picture = Picture::factory()->create([
            'path_original' => $imagePath,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => '{"description": "Analyzed by Claude"}',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->prompt(
            'You are an image analyzer',
            'Describe this image',
            $picture
        );

        $this->assertIsArray($result);
        $this->assertEquals('Analyzed by Claude', $result['description']);
    }

    #[Test]
    public function it_throws_exception_when_picture_has_no_original_path(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Picture has no original path');

        $this->service->prompt(
            'You are an image analyzer',
            'Describe this image',
            $picture
        );
    }

    #[Test]
    public function it_throws_exception_when_picture_not_found_in_storage(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => 'non-existent/image.jpg',
        ]);

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get picture content from storage');

        $this->service->prompt(
            'You are an image analyzer',
            'Describe this image',
            $picture
        );
    }

    #[Test]
    public function it_creates_notification_on_error(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        Http::fake();

        try {
            $this->service->prompt(
                'You are an image analyzer',
                'Describe this image',
                $picture
            );
        } catch (RuntimeException) {
            // Expected
        }

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'type' => 'error',
        ]);
    }
}
