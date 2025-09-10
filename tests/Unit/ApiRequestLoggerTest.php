<?php

namespace Tests\Unit;

use App\Models\ApiRequestLog;
use App\Services\ApiRequestLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRequestLoggerTest extends TestCase
{
    use RefreshDatabase;

    private ApiRequestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new ApiRequestLogger;
    }

    public function test_log_success_creates_log_entry(): void
    {
        $log = $this->logger->logSuccess(
            'openai',
            'gpt-4o-mini',
            'https://api.openai.com/v1/chat/completions',
            'You are a translator',
            'Translate hello to French',
            [
                'choices' => [['message' => ['content' => '{"translation": "Bonjour"}']]],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ],
            1.234,
            200,
            false,
            ['test' => 'metadata']
        );

        $this->assertInstanceOf(ApiRequestLog::class, $log);
        $this->assertEquals('openai', $log->provider);
        $this->assertEquals('gpt-4o-mini', $log->model);
        $this->assertEquals('success', $log->status);
        $this->assertEquals(200, $log->http_status_code);
        $this->assertEquals(10, $log->prompt_tokens);
        $this->assertEquals(5, $log->completion_tokens);
        $this->assertEquals(15, $log->total_tokens);
        $this->assertEquals(1.234, $log->response_time);
        $this->assertFalse($log->cached);
        $this->assertNotNull($log->estimated_cost);
    }

    public function test_log_error_creates_log_entry(): void
    {
        $log = $this->logger->logError(
            'anthropic',
            'claude-3-sonnet',
            'https://api.anthropic.com/v1/messages',
            'System prompt',
            'User prompt',
            'Connection timeout',
            5.0,
            null,
            ['error_type' => 'timeout']
        );

        $this->assertInstanceOf(ApiRequestLog::class, $log);
        $this->assertEquals('anthropic', $log->provider);
        $this->assertEquals('error', $log->status);
        $this->assertEquals('Connection timeout', $log->error_message);
        $this->assertEquals(5.0, $log->response_time);
        $this->assertNull($log->http_status_code);
    }

    public function test_log_timeout_creates_log_entry(): void
    {
        $log = $this->logger->logTimeout(
            'openai',
            'gpt-4o',
            'https://api.openai.com/v1/chat/completions',
            'System',
            'User',
            120.0,
            ['timeout_seconds' => 120]
        );

        $this->assertInstanceOf(ApiRequestLog::class, $log);
        $this->assertEquals('timeout', $log->status);
        $this->assertEquals('Request timeout', $log->error_message);
        $this->assertEquals(120.0, $log->response_time);
    }

    public function test_cost_estimation_for_openai(): void
    {
        $log = $this->logger->logSuccess(
            'openai',
            'gpt-4o-mini',
            'https://api.openai.com/v1/chat/completions',
            'System',
            'User',
            [
                'usage' => [
                    'prompt_tokens' => 1000,
                    'completion_tokens' => 1000,
                    'total_tokens' => 2000,
                ],
            ],
            1.0,
            200,
            false
        );

        // gpt-4o-mini: $0.00015 per 1k input, $0.0006 per 1k output
        $expectedCost = 0.00075; // (1000/1000 * 0.00015) + (1000/1000 * 0.0006)
        $this->assertEquals($expectedCost, (float) $log->estimated_cost);
    }

    public function test_cost_estimation_for_anthropic(): void
    {
        $log = $this->logger->logSuccess(
            'anthropic',
            'claude-3-5-sonnet-20241022',
            'https://api.anthropic.com/v1/messages',
            'System',
            'User',
            [
                'usage' => [
                    'input_tokens' => 1000,
                    'output_tokens' => 1000,
                ],
            ],
            1.0,
            200,
            false
        );

        // claude-3-5-sonnet: $0.003 per 1k input, $0.015 per 1k output
        $expectedCost = 0.018; // (1000/1000 * 0.003) + (1000/1000 * 0.015)
        $this->assertEquals($expectedCost, (float) $log->estimated_cost);
    }

    public function test_cached_response_logged_correctly(): void
    {
        $log = $this->logger->logSuccess(
            'openai',
            'gpt-4o-mini',
            'https://api.openai.com/v1/chat/completions',
            'System',
            'User',
            ['cached_response' => true],
            0.001,
            200,
            true // cached = true
        );

        $this->assertTrue($log->cached);
        $this->assertEquals(0.001, $log->response_time);
    }

    public function test_get_statistics_returns_correct_data(): void
    {
        // Create test logs
        $this->logger->logSuccess(
            'openai', 'gpt-4o-mini', 'https://api.openai.com', 'S1', 'U1',
            ['usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150]],
            1.0, 200, false
        );

        $this->logger->logSuccess(
            'anthropic', 'claude-3-haiku', 'https://api.anthropic.com', 'S2', 'U2',
            ['usage' => ['input_tokens' => 200, 'output_tokens' => 100]],
            2.0, 200, true
        );

        $this->logger->logError(
            'openai', 'gpt-4o', 'https://api.openai.com', 'S3', 'U3',
            'Error message', 3.0, 500
        );

        $stats = $this->logger->getStatistics(30);

        $this->assertEquals(3, $stats['total_requests']);
        $this->assertEquals(2, $stats['successful_requests']);
        $this->assertEquals(1, $stats['error_requests']);
        $this->assertEquals(1, $stats['cached_requests']);
        $this->assertArrayHasKey('total_cost', $stats);
        $this->assertArrayHasKey('average_response_time', $stats);
        $this->assertArrayHasKey('by_provider', $stats);
        $this->assertArrayHasKey('by_status', $stats);
    }

    public function test_statistics_by_provider(): void
    {
        // Create logs for different providers
        for ($i = 0; $i < 3; $i++) {
            $this->logger->logSuccess(
                'openai', 'gpt-4o-mini', 'https://api.openai.com', 'S', 'U',
                ['usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150]],
                1.0, 200, false
            );
        }

        for ($i = 0; $i < 2; $i++) {
            $this->logger->logSuccess(
                'anthropic', 'claude-3-haiku', 'https://api.anthropic.com', 'S', 'U',
                ['usage' => ['input_tokens' => 100, 'output_tokens' => 50]],
                2.0, 200, false
            );
        }

        $stats = $this->logger->getStatistics(30);

        $this->assertArrayHasKey('openai', $stats['by_provider']);
        $this->assertArrayHasKey('anthropic', $stats['by_provider']);
        $this->assertEquals(3, $stats['by_provider']['openai']['count']);
        $this->assertEquals(2, $stats['by_provider']['anthropic']['count']);
    }
}
