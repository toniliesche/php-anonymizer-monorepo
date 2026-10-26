# Persona strategies

Install `php-anonymizer/anonymizer-personas`; Composer installs the required
core package. `PersonaReplacementStrategyFactory::create($locale)` returns
strategies with IDs `persona.name`, `persona.first_name`,
`persona.last_name`, `persona.email`, `persona.username`, `persona.company`,
`persona.phone`, `persona.street`, `persona.street_number`, `persona.city`,
`persona.postcode`, and `persona.country`.

Accepted base locales are `en`, `de`, `es`, `fr`, `it`, `nl`, and `pt`.
Regional catalogs are available for `en_IE`, `de_AT`, `de_CH`, and `nl_BE`;
matching base-region forms (`en_GB`, `de_DE`, `nl_NL`) and `pt_PT` are also
accepted. Locale matching ignores case and surrounding whitespace and accepts
`-` or `_` separators. Other regional combinations are rejected. Catalog
values are deterministic fixed placeholders, with email addresses in the
reserved `example.invalid` domain. They are not production identity data.

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md)
and [extension author guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/extension-author-guide.md).

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
