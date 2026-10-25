# PHP Anonymizer

PHP Anonymizer is a PHP 8.5+ library for applying reusable profiles to arrays,
documents, streams, and optionally objects. The framework-independent core
returns transformed data without changing the input.

Install the core package with Composer:

```sh
composer require php-anonymizer/anonymizer
```

Build a profile and façade, then anonymize an associative array:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]);
$anonymizer = AnonymizerBuilder::create()->withProfile($profile)->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'ada@example.test']);

if ($safe !== ['email' => '[REDACTED]']) {
    throw new RuntimeException('The quick-start example did not redact the email.');
}
```

Profiles default to `redact` mode. Use `preserve` mode when you want an
allow-list: selected strings pass through and other strings are redacted.
Nested map and list rules preserve the input shape. Read the [user guide](docs/user-guide.md)
for the application walkthrough and [profile schema](docs/profile-schema.md)
for every supported rule and option.

## Optional integrations

Install only the packages you need:

- [Symfony Bundle](packages/anonymizer-bundle/README.md) for Symfony service
  registration, profile configuration, and tagged extensions.
- [Laravel integration](packages/anonymizer-laravel/README.md) for Laravel 13
  service-container registration.
- [Symfony Serializer adapter](packages/anonymizer-symfony-serializer/README.md)
  for opt-in object anonymization.
- [Faker strategies](packages/anonymizer-faker/README.md) or [fixed personas](packages/anonymizer-personas/README.md)
  for synthetic replacement values.
- [Pseudonymization](packages/anonymizer-pseudonymization/README.md) for
  context-aware keyed pseudonyms.

Extensions can also be implemented in plain PHP. See the
[extension author guide](docs/extension-author-guide.md).

## Documentation

- [User guide](docs/user-guide.md)
- [Profile schema reference](docs/profile-schema.md)
- [Extension author guide](docs/extension-author-guide.md)
- [Migration to 2.0](docs/migration-v2.md)
- [Legacy rule import](docs/legacy-rule-import.md)
- [API surface](docs/api-surface-v2.md)
- [Release and publishing](docs/release-and-publishing.md) for maintainers

The project is released under the [MIT license](LICENSE).
