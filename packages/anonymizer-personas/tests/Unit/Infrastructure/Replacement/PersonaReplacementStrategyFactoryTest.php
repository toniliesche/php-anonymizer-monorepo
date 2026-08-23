<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Tests\Unit\Infrastructure\Replacement;

use InvalidArgumentException;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\Persona;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaCatalog;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaField;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaLocale;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategy;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Persona::class)]
#[CoversClass(PersonaCatalog::class)]
#[CoversClass(PersonaField::class)]
#[CoversClass(PersonaLocale::class)]
#[CoversClass(PersonaReplacementStrategy::class)]
#[CoversClass(PersonaReplacementStrategyFactory::class)]
final class PersonaReplacementStrategyFactoryTest extends TestCase
{
    public function testExposesCanonicalStrategyIds(): void
    {
        self::assertSame(PersonaReplacementStrategyFactory::IDS, PersonaReplacementStrategyFactory::ids());
    }

    public function testCreatesStableEuropeanPersonaValues(): void
    {
        $strategies = PersonaReplacementStrategyFactory::create('pt_PT');

        self::assertCount(12, $strategies);
        self::assertSame('António Silva', $strategies[0]->replace('ignored'));
        self::assertSame('persona.email', $strategies[3]->id());
        self::assertSame('antonio.silva@example.invalid', $strategies[3]->replace('ignored'));
        foreach (PersonaField::cases() as $field) {
            self::assertNotSame('', PersonaCatalog::for(PersonaLocale::Portuguese)->value($field));
        }
    }

    public function testAcceptsEuropeanLanguageAndRegionForms(): void
    {
        self::assertSame('de', PersonaLocale::fromLocale('DE-de')->value);
        self::assertSame('de', PersonaLocale::fromLocale('  DE-de  ')->value);
        self::assertSame('en', PersonaLocale::fromLocale('en_GB')->value);
        self::assertSame('nl', PersonaLocale::fromLocale('nl')->value);
        self::assertSame('en_ie', PersonaLocale::fromLocale('en_IE')->value);
        self::assertSame('de_at', PersonaLocale::fromLocale('de_AT')->value);
        self::assertSame('de_ch', PersonaLocale::fromLocale('de_CH')->value);
        self::assertSame('nl_be', PersonaLocale::fromLocale('nl_BE')->value);

        self::assertSame('Seán Murphy', PersonaReplacementStrategyFactory::create('en_IE')[0]->replace('x'));
        self::assertSame('Franz Huber', PersonaReplacementStrategyFactory::create('de_AT')[0]->replace('x'));
        self::assertSame('Hans Müller', PersonaReplacementStrategyFactory::create('de_CH')[0]->replace('x'));
        self::assertSame('Jan Peeters', PersonaReplacementStrategyFactory::create('nl_BE')[0]->replace('x'));
        self::assertSame('Roma', PersonaCatalog::for(PersonaLocale::Italian)->value(PersonaField::City));
        self::assertSame('Lisboa', PersonaCatalog::for(PersonaLocale::Portuguese)->value(PersonaField::City));
        self::assertSame('Wien', PersonaCatalog::for(PersonaLocale::Austrian)->value(PersonaField::City));
        self::assertSame('Antwerpen', PersonaCatalog::for(PersonaLocale::BelgianDutch)->value(PersonaField::City));
        self::assertSame('Joe Bloggs', PersonaCatalog::for(PersonaLocale::English)->name);
        self::assertSame('Max Mustermann', PersonaCatalog::for(PersonaLocale::German)->name);
        self::assertSame('Juan García', PersonaCatalog::for(PersonaLocale::Spanish)->name);
        self::assertSame('Jean Dupont', PersonaCatalog::for(PersonaLocale::French)->name);
        self::assertSame('Mario Rossi', PersonaCatalog::for(PersonaLocale::Italian)->name);
        self::assertSame('Jan Jansen', PersonaCatalog::for(PersonaLocale::Dutch)->name);
        self::assertSame('António Silva', PersonaCatalog::for(PersonaLocale::Portuguese)->name);
        self::assertSame('Franz Huber', PersonaCatalog::for(PersonaLocale::Austrian)->name);
        self::assertSame('Jan Peeters', PersonaCatalog::for(PersonaLocale::BelgianDutch)->name);
    }

    public function testRejectsNonEuropeanRegionalLocaleAndUnknownStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PersonaLocale::fromLocale('en_US');
    }

    public function testRejectsUnknownStrategyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PersonaReplacementStrategyFactory::createStrategy('en', 'persona.unknown');
    }

    public function testCreatesOneStrategyThroughTheStableReplacementPort(): void
    {
        $strategy = PersonaReplacementStrategyFactory::createReplacementStrategy('en', 'persona.name');

        self::assertSame('persona.name', $strategy->id());
    }
}
