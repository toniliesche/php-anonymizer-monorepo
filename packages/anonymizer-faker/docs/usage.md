# Faker strategies

Install `php-anonymizer/anonymizer-faker` with the core and create the
documented `FakerReplacementStrategyFactory` for the desired locale and seed.
Register its strategies with `withReplacementStrategies()` or through the
Bundle/Laravel integration. Strategy IDs are stable `faker.*` identifiers.

See the repository [`user guide`](../../../docs/user-guide.md) and
[`extension author guide`](../../../docs/extension-author-guide.md).

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;

$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('person', ['mode' => 'redact', 'rules' => [['field' => 'email', 'strategy' => 'faker.email']]]))
    ->withReplacementStrategies(FakerReplacementStrategyFactory::create('en_US', 1234))
    ->build();
$safe = $anonymizer->anonymize('person', ['email' => 'ada@example.test']);

if (!is_string($safe['email']) || $safe['email'] === 'ada@example.test' || !str_contains($safe['email'], '@')) {
    throw new RuntimeException('The seeded Faker strategy example failed.');
}
```
