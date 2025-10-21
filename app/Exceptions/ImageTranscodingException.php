<?php

namespace App\Exceptions;

use App\Enums\ImageTranscodingError;
use Exception;
use Throwable;

class ImageTranscodingException extends Exception
{
    protected ImageTranscodingError $errorCode;

    protected string $driverUsed;

    protected ?string $fallbackAttempted;

    /**
     * @var array<string, mixed>
     */
    protected array $context;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        ImageTranscodingError $errorCode,
        string $driverUsed,
        string $message = '',
        ?string $fallbackAttempted = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->driverUsed = $driverUsed;
        $this->fallbackAttempted = $fallbackAttempted;
        $this->context = $context;

        // Build comprehensive error message
        $fullMessage = $message ?: $errorCode->getDescription();
        if ($driverUsed) {
            $fullMessage .= " (Driver: {$driverUsed})";
        }
        if ($fallbackAttempted) {
            $fullMessage .= " (Fallback attempted: {$fallbackAttempted})";
        }

        parent::__construct($fullMessage, 0, $previous);
    }

    /**
     * Create an exception from a failed Imagick operation
     *
     * @param  array<string, mixed>  $context
     */
    public static function imagickFailed(string $message, array $context = [], ?Throwable $previous = null): self
    {
        return new self(
            ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'Imagick',
            $message,
            null,
            $context,
            $previous
        );
    }

    /**
     * Create an exception from a failed GD operation
     *
     * @param  array<string, mixed>  $context
     */
    public static function gdFailed(string $message, array $context = [], ?Throwable $previous = null): self
    {
        return new self(
            ImageTranscodingError::GD_ENCODING_FAILED,
            'GD',
            $message,
            null,
            $context,
            $previous
        );
    }

    /**
     * Create an exception when all drivers fail
     *
     * @param  array<string, mixed>  $attempts
     * @param  array<string, mixed>  $context
     */
    public static function allDriversFailed(array $attempts, array $context = []): self
    {
        $message = 'All available drivers failed. Attempts: '.implode(', ', array_keys($attempts));

        return new self(
            ImageTranscodingError::ALL_DRIVERS_FAILED,
            'Multiple',
            $message,
            null,
            array_merge($context, ['attempts' => $attempts])
        );
    }

    /**
     * Create an exception for empty output
     *
     * @param  array<string, mixed>  $context
     */
    public static function emptyOutput(string $driver, array $context = []): self
    {
        return new self(
            ImageTranscodingError::EMPTY_OUTPUT,
            $driver,
            'Image encoding resulted in empty output (0 bytes)',
            null,
            $context
        );
    }

    /**
     * Create an exception for unsupported format
     *
     * @param  array<string, mixed>  $context
     */
    public static function unsupportedFormat(string $format, string $driver, array $context = []): self
    {
        return new self(
            ImageTranscodingError::UNSUPPORTED_FORMAT,
            $driver,
            "Format '{$format}' is not supported by driver '{$driver}'",
            null,
            $context
        );
    }

    /**
     * Create an exception for resource limits
     *
     * @param  array<string, mixed>  $context
     */
    public static function resourceLimitExceeded(string $driver, string $limitType, array $context = []): self
    {
        return new self(
            ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED,
            $driver,
            "Resource limit exceeded: {$limitType}",
            null,
            $context
        );
    }

    /**
     * Get the specific error code
     */
    public function getErrorCode(): ImageTranscodingError
    {
        return $this->errorCode;
    }

    /**
     * Get the driver that was used when the error occurred
     */
    public function getDriverUsed(): string
    {
        return $this->driverUsed;
    }

    /**
     * Get the fallback driver that was attempted (if any)
     */
    public function getFallbackAttempted(): ?string
    {
        return $this->fallbackAttempted;
    }

    /**
     * Get additional context information
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set fallback information when a fallback is attempted
     */
    public function setFallbackAttempted(string $fallbackDriver): self
    {
        $this->fallbackAttempted = $fallbackDriver;

        return $this;
    }

    /**
     * Determine if this error should trigger a fallback
     */
    public function shouldTriggerFallback(): bool
    {
        return $this->errorCode->shouldTriggerFallback();
    }

    /**
     * Get error severity for logging
     */
    public function getSeverity(): string
    {
        return $this->errorCode->getSeverity();
    }

    /**
     * Convert to array for logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode->value,
            'driver_used' => $this->driverUsed,
            'fallback_attempted' => $this->fallbackAttempted,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'severity' => $this->getSeverity(),
        ];
    }
}
