# Persona strategies

Install `php-anonymizer/anonymizer-personas` with the core and create persona
strategies with `PersonaReplacementStrategyFactory::create()`. Locale input is
validated, regional catalogs are deterministic, and email values use the
non-deliverable `example.invalid` domain. Register the resulting strategies by
their stable `persona.*` IDs.

See the repository [`user guide`](../../../docs/user-guide.md) and
[`extension author guide`](../../../docs/extension-author-guide.md).

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;

$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('person', ['mode' => 'redact', 'rules' => [['field' => 'email', 'strategy' => 'persona.email']]]))
    ->withReplacementStrategies(PersonaReplacementStrategyFactory::create('de'))
    ->build();
$safe = $anonymizer->anonymize('person', ['email' => 'ada@example.test']);

if (!is_string($safe['email']) || $safe['email'] === 'ada@example.test' || !str_ends_with($safe['email'], '@example.invalid')) {
    throw new RuntimeException('The persona strategy example failed.');
}
```
