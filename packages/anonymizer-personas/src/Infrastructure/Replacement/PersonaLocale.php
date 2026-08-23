<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

use InvalidArgumentException;
use function mb_strtolower;

// @internal
enum PersonaLocale: string
{
    case English = 'en';
    case German = 'de';
    case Spanish = 'es';
    case French = 'fr';
    case Italian = 'it';
    case Dutch = 'nl';
    case Portuguese = 'pt';
    case Irish = 'en_ie';
    case Austrian = 'de_at';
    case Swiss = 'de_ch';
    case BelgianDutch = 'nl_be';

    public static function fromLocale(string $locale): self
    {
        $normalized = mb_strtolower(str_replace('-', '_', trim($locale)), 'UTF-8');
        $parts = explode('_', $normalized);
        $language = $parts[0];
        $region = $parts[1] ?? null;
        $regional = match ($normalized) {
            'en_ie' => self::Irish,
            'de_at' => self::Austrian,
            'de_ch' => self::Swiss,
            'nl_be' => self::BelgianDutch,
            default => null,
        };
        if ($regional !== null) {
            return $regional;
        }
        $allowedRegions = ['en' => 'gb', 'de' => 'de', 'es' => 'es', 'fr' => 'fr', 'it' => 'it', 'nl' => 'nl', 'pt' => 'pt'];
        if (!isset($allowedRegions[$language]) || ($region !== null && $region !== $allowedRegions[$language])) {
            throw new InvalidArgumentException(sprintf('Unsupported European persona locale "%s". Supported locales: en, de, es, fr, it, nl, pt.', $locale));
        }

        return self::from($language);
    }
}
