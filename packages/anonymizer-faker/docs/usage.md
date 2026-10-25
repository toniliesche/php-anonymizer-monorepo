# Faker strategies

Install `php-anonymizer/anonymizer-faker`; Composer also installs the required
core package and FakerPHP. Create strategies with
`FakerReplacementStrategyFactory::create($locale, $seed)` and register them
with `withReplacementStrategies()`. The locale uses FakerPHP's locale names.
Choose a seed to make output reproducible for a fixed FakerPHP version and
call sequence; dependency upgrades can change generated values.

Supported IDs are `faker.building_number`, `faker.city`, `faker.company`,
`faker.country`, `faker.email`, `faker.first_name`, `faker.house_number`,
`faker.last_name`, `faker.name`, `faker.password`, `faker.postcode`,
`faker.street`, `faker.street_number`, `faker.username`, and `faker.zip`.
The postcode and zip IDs map to the same Faker formatter.

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md)
and [extension author guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/extension-author-guide.md).

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
