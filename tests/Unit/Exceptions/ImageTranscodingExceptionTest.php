<?php

namespace Tests\Unit\Exceptions;

use App\Enums\ImageTranscodingError;
use App\Exceptions\ImageTranscodingException;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ImageTranscodingException::class)]
class ImageTranscodingExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_exception_with_basic_constructor(): void
    {
        $errorCode = ImageTranscodingError::IMAGICK_ENCODING_FAILED;
        $driverUsed = 'Imagick';
        $message = 'Custom error message';
        $fallbackAttempted = 'GD';
        $context = ['file' => 'test.jpg', 'size' => 1024];
        $previous = new Exception('Previous exception');

        $exception = new ImageTranscodingException(
            $errorCode,
            $driverUsed,
            $message,
            $fallbackAttempted,
            $context,
            $previous
        );

        $this->assertSame($errorCode, $exception->getErrorCode());
        $this->assertSame($driverUsed, $exception->getDriverUsed());
        $this->assertSame($fallbackAttempted, $exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString($message, $exception->getMessage());
        $this->assertStringContainsString("(Driver: {$driverUsed})", $exception->getMessage());
        $this->assertStringContainsString("(Fallback attempted: {$fallbackAttempted})", $exception->getMessage());
    }

    #[Test]
    public function it_creates_exception_with_minimal_constructor(): void
    {
        $errorCode = ImageTranscodingError::GD_ENCODING_FAILED;
        $driverUsed = 'GD';

        $exception = new ImageTranscodingException($errorCode, $driverUsed);

        $this->assertSame($errorCode, $exception->getErrorCode());
        $this->assertSame($driverUsed, $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame([], $exception->getContext());
        $this->assertNull($exception->getPrevious());
        $this->assertStringContainsString($errorCode->getDescription(), $exception->getMessage());
        $this->assertStringContainsString("(Driver: {$driverUsed})", $exception->getMessage());
    }

    #[Test]
    public function it_creates_exception_without_fallback_attempted(): void
    {
        $errorCode = ImageTranscodingError::EMPTY_OUTPUT;
        $driverUsed = 'Imagick';
        $message = 'Empty output error';

        $exception = new ImageTranscodingException(
            $errorCode,
            $driverUsed,
            $message,
            null
        );

        $this->assertNull($exception->getFallbackAttempted());
        $this->assertStringNotContainsString('Fallback attempted', $exception->getMessage());
    }

    #[Test]
    public function it_creates_imagick_failed_exception(): void
    {
        $message = 'Imagick encoding failed';
        $context = ['format' => 'avif', 'quality' => 90];
        $previous = new Exception('Original error');

        $exception = ImageTranscodingException::imagickFailed($message, $context, $previous);

        $this->assertSame(ImageTranscodingError::IMAGICK_ENCODING_FAILED, $exception->getErrorCode());
        $this->assertSame('Imagick', $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString($message, $exception->getMessage());
    }

    #[Test]
    public function it_creates_gd_failed_exception(): void
    {
        $message = 'GD encoding failed';
        $context = ['format' => 'webp', 'quality' => 85];
        $previous = new Exception('GD error');

        $exception = ImageTranscodingException::gdFailed($message, $context, $previous);

        $this->assertSame(ImageTranscodingError::GD_ENCODING_FAILED, $exception->getErrorCode());
        $this->assertSame('GD', $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString($message, $exception->getMessage());
    }

    #[Test]
    public function it_creates_all_drivers_failed_exception(): void
    {
        $attempts = [
            'Imagick' => 'Imagick error message',
            'GD' => 'GD error message',
        ];
        $context = ['file' => 'test.png'];

        $exception = ImageTranscodingException::allDriversFailed($attempts, $context);

        $this->assertSame(ImageTranscodingError::ALL_DRIVERS_FAILED, $exception->getErrorCode());
        $this->assertSame('Multiple', $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertStringContainsString('All available drivers failed', $exception->getMessage());
        $this->assertStringContainsString('Imagick, GD', $exception->getMessage());

        $expectedContext = $exception->getContext();
        $this->assertArrayHasKey('attempts', $expectedContext);
        $this->assertArrayHasKey('file', $expectedContext);
        $this->assertSame($attempts, $expectedContext['attempts']);
        $this->assertSame('test.png', $expectedContext['file']);
    }

    #[Test]
    public function it_creates_empty_output_exception(): void
    {
        $driver = 'Imagick';
        $context = ['size' => 0, 'format' => 'avif'];

        $exception = ImageTranscodingException::emptyOutput($driver, $context);

        $this->assertSame(ImageTranscodingError::EMPTY_OUTPUT, $exception->getErrorCode());
        $this->assertSame($driver, $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertStringContainsString('Image encoding resulted in empty output (0 bytes)', $exception->getMessage());
    }

    #[Test]
    public function it_creates_unsupported_format_exception(): void
    {
        $format = 'avif';
        $driver = 'GD';
        $context = ['requested_format' => 'avif'];

        $exception = ImageTranscodingException::unsupportedFormat($format, $driver, $context);

        $this->assertSame(ImageTranscodingError::UNSUPPORTED_FORMAT, $exception->getErrorCode());
        $this->assertSame($driver, $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertStringContainsString("Format '{$format}' is not supported by driver '{$driver}'", $exception->getMessage());
    }

    #[Test]
    public function it_creates_resource_limit_exceeded_exception(): void
    {
        $driver = 'Imagick';
        $limitType = 'memory';
        $context = ['limit' => '256MB', 'required' => '512MB'];

        $exception = ImageTranscodingException::resourceLimitExceeded($driver, $limitType, $context);

        $this->assertSame(ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED, $exception->getErrorCode());
        $this->assertSame($driver, $exception->getDriverUsed());
        $this->assertNull($exception->getFallbackAttempted());
        $this->assertSame($context, $exception->getContext());
        $this->assertStringContainsString("Resource limit exceeded: {$limitType}", $exception->getMessage());
    }

    #[Test]
    public function it_sets_fallback_attempted(): void
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'Imagick'
        );

        $this->assertNull($exception->getFallbackAttempted());

        $result = $exception->setFallbackAttempted('GD');

        $this->assertSame($exception, $result);
        $this->assertSame('GD', $exception->getFallbackAttempted());
    }

    #[Test]
    #[DataProvider('shouldTriggerFallbackProvider')]
    public function it_determines_if_should_trigger_fallback(ImageTranscodingError $errorCode, bool $expectedResult): void
    {
        $exception = new ImageTranscodingException($errorCode, 'TestDriver');

        $this->assertSame($expectedResult, $exception->shouldTriggerFallback());
    }

    public static function shouldTriggerFallbackProvider(): array
    {
        return [
            'imagick_encoding_failed' => [ImageTranscodingError::IMAGICK_ENCODING_FAILED, true],
            'empty_output' => [ImageTranscodingError::EMPTY_OUTPUT, true],
            'unsupported_format' => [ImageTranscodingError::UNSUPPORTED_FORMAT, true],
            'gd_encoding_failed' => [ImageTranscodingError::GD_ENCODING_FAILED, false],
            'resource_limit_exceeded' => [ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED, false],
            'all_drivers_failed' => [ImageTranscodingError::ALL_DRIVERS_FAILED, false],
        ];
    }

    #[Test]
    #[DataProvider('getSeverityProvider')]
    public function it_gets_error_severity(ImageTranscodingError $errorCode, string $expectedSeverity): void
    {
        $exception = new ImageTranscodingException($errorCode, 'TestDriver');

        $this->assertSame($expectedSeverity, $exception->getSeverity());
    }

    public static function getSeverityProvider(): array
    {
        return [
            'resource_limit_exceeded' => [ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED, 'critical'],
            'memory_limit_exceeded' => [ImageTranscodingError::MEMORY_LIMIT_EXCEEDED, 'critical'],
            'image_too_large' => [ImageTranscodingError::IMAGE_TOO_LARGE, 'critical'],
            'all_drivers_failed' => [ImageTranscodingError::ALL_DRIVERS_FAILED, 'error'],
            'driver_not_available' => [ImageTranscodingError::DRIVER_NOT_AVAILABLE, 'error'],
            'imagick_encoding_failed' => [ImageTranscodingError::IMAGICK_ENCODING_FAILED, 'warning'],
            'unsupported_format' => [ImageTranscodingError::UNSUPPORTED_FORMAT, 'warning'],
            'gd_encoding_failed' => [ImageTranscodingError::GD_ENCODING_FAILED, 'info'],
            'empty_output' => [ImageTranscodingError::EMPTY_OUTPUT, 'info'],
        ];
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $errorCode = ImageTranscodingError::IMAGICK_ENCODING_FAILED;
        $driverUsed = 'Imagick';
        $message = 'Test error';
        $fallbackAttempted = 'GD';
        $context = ['file' => 'test.jpg'];

        $exception = new ImageTranscodingException(
            $errorCode,
            $driverUsed,
            $message,
            $fallbackAttempted,
            $context
        );

        $array = $exception->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('driver_used', $array);
        $this->assertArrayHasKey('fallback_attempted', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('severity', $array);

        $this->assertSame($errorCode->value, $array['error_code']);
        $this->assertSame($driverUsed, $array['driver_used']);
        $this->assertSame($fallbackAttempted, $array['fallback_attempted']);
        $this->assertSame($context, $array['context']);
        $this->assertSame($exception->getSeverity(), $array['severity']);
        $this->assertStringContainsString($message, $array['message']);
    }

    #[Test]
    public function it_converts_to_array_without_fallback(): void
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::GD_ENCODING_FAILED,
            'GD'
        );

        $array = $exception->toArray();

        $this->assertNull($array['fallback_attempted']);
        $this->assertSame([], $array['context']);
    }

    #[Test]
    public function it_uses_error_description_when_no_message_provided(): void
    {
        $errorCode = ImageTranscodingError::MEMORY_LIMIT_EXCEEDED;
        $exception = new ImageTranscodingException($errorCode, 'Imagick', '');

        $this->assertStringContainsString($errorCode->getDescription(), $exception->getMessage());
    }

    #[Test]
    public function it_handles_empty_driver_name(): void
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::DRIVER_NOT_AVAILABLE,
            '',
            'No driver available'
        );

        $this->assertSame('', $exception->getDriverUsed());
        $this->assertStringNotContainsString('(Driver: )', $exception->getMessage());
    }

    #[Test]
    public function it_creates_minimal_static_exceptions(): void
    {
        $imagickException = ImageTranscodingException::imagickFailed('Test error');
        $this->assertSame([], $imagickException->getContext());
        $this->assertNull($imagickException->getPrevious());

        $gdException = ImageTranscodingException::gdFailed('Test error');
        $this->assertSame([], $gdException->getContext());
        $this->assertNull($gdException->getPrevious());

        $emptyException = ImageTranscodingException::emptyOutput('TestDriver');
        $this->assertSame([], $emptyException->getContext());

        $unsupportedException = ImageTranscodingException::unsupportedFormat('format', 'driver');
        $this->assertSame([], $unsupportedException->getContext());

        $resourceException = ImageTranscodingException::resourceLimitExceeded('driver', 'memory');
        $this->assertSame([], $resourceException->getContext());

        $allFailedException = ImageTranscodingException::allDriversFailed(['driver1' => 'error1']);
        $this->assertArrayHasKey('attempts', $allFailedException->getContext());
    }
}
