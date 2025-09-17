<?php

namespace App\Enums;

enum ImageTranscodingError: string
{
    case IMAGICK_ENCODING_FAILED = 'imagick_encoding_failed';
    case GD_ENCODING_FAILED = 'gd_encoding_failed';
    case DRIVER_NOT_AVAILABLE = 'driver_not_available';
    case EMPTY_OUTPUT = 'empty_output';
    case RESOURCE_LIMIT_EXCEEDED = 'resource_limit_exceeded';
    case UNSUPPORTED_FORMAT = 'unsupported_format';
    case INVALID_SOURCE = 'invalid_source';
    case ALL_DRIVERS_FAILED = 'all_drivers_failed';
    case MEMORY_LIMIT_EXCEEDED = 'memory_limit_exceeded';
    case IMAGE_TOO_LARGE = 'image_too_large';

    /**
     * Get a human-readable description of the error
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::IMAGICK_ENCODING_FAILED => 'Échec de l\'encodage avec Imagick',
            self::GD_ENCODING_FAILED => 'Échec de l\'encodage avec GD',
            self::DRIVER_NOT_AVAILABLE => 'Driver d\'image non disponible',
            self::EMPTY_OUTPUT => 'L\'encodage a produit un résultat vide',
            self::RESOURCE_LIMIT_EXCEEDED => 'Limites de ressources dépassées',
            self::UNSUPPORTED_FORMAT => 'Format d\'image non supporté',
            self::INVALID_SOURCE => 'Source d\'image invalide',
            self::ALL_DRIVERS_FAILED => 'Échec de tous les drivers disponibles',
            self::MEMORY_LIMIT_EXCEEDED => 'Limite de mémoire dépassée',
            self::IMAGE_TOO_LARGE => 'Image trop large pour être traitée',
        };
    }

    /**
     * Determine if this error should trigger a fallback to another driver
     */
    public function shouldTriggerFallback(): bool
    {
        return match ($this) {
            self::IMAGICK_ENCODING_FAILED,
            self::EMPTY_OUTPUT,
            self::UNSUPPORTED_FORMAT => true,
            default => false,
        };
    }

    /**
     * Get the severity level for logging and notifications
     */
    public function getSeverity(): string
    {
        return match ($this) {
            self::RESOURCE_LIMIT_EXCEEDED,
            self::MEMORY_LIMIT_EXCEEDED,
            self::IMAGE_TOO_LARGE => 'critical',
            self::ALL_DRIVERS_FAILED,
            self::DRIVER_NOT_AVAILABLE => 'error',
            self::IMAGICK_ENCODING_FAILED,
            self::UNSUPPORTED_FORMAT => 'warning',
            default => 'info',
        };
    }
}
