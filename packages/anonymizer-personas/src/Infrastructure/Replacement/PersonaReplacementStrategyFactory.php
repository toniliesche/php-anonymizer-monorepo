<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;

final class PersonaReplacementStrategyFactory
{
    /** @var list<string> */
    public const array IDS = ['persona.name', 'persona.first_name', 'persona.last_name', 'persona.email', 'persona.username', 'persona.company', 'persona.phone', 'persona.street', 'persona.street_number', 'persona.city', 'persona.postcode', 'persona.country'];

    /**
     * @return list<PersonaReplacementStrategy>
     */
    public static function create(string $locale = 'en'): array
    {
        return array_map(static fn (string $id): PersonaReplacementStrategy => self::createStrategy($locale, $id), self::IDS);
    }

    public static function createStrategy(string $locale, string $id): PersonaReplacementStrategy
    {
        if (!in_array($id, self::IDS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported persona strategy ID "%s".', $id));
        }

        $field = PersonaField::from(str_replace('persona.', '', $id));

        return new PersonaReplacementStrategy(PersonaCatalog::for(PersonaLocale::fromLocale($locale)), $field);
    }

    public static function createReplacementStrategy(string $locale, string $id): ReplacementStrategyInterface
    {
        return self::createStrategy($locale, $id);
    }
}
