# Symfony Bundle usage

Install `php-anonymizer/anonymizer-bundle`; Composer installs the core package
and required Symfony components, including Symfony Serializer. Symfony Flex
registers `PhpAnonymizer\AnonymizerBundle\AnonymizerBundle` and copies the
starter config. Without Flex, add the bundle to `config/bundles.php`:

```php
return [
    PhpAnonymizer\AnonymizerBundle\AnonymizerBundle::class => ['all' => true],
];
```

Configure profiles under `anonymizer.profiles` in
`config/packages/anonymizer.yaml`. The Bundle validates profiles during
container compilation and exposes the public, autowireable
`PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`.
Configured profiles are composed before services tagged
`anonymizer.profile_provider`.

```yaml
# config/packages/anonymizer.yaml
anonymizer:
  profiles:
    customer:
      mode: preserve
      rules:
        - field: name
        - field: email
          strategy: star_mask
  limits:
    max_input_bytes: 10485760
    max_depth: 64
    max_visited_nodes: 100000
    max_collection_entries: 50000
    max_embedded_payload_depth: 8
```

All five limits are positive integers. Omitted limits use the defaults shown.
The Bundle also provides the `yaml` payload codec automatically for map and
list documents. Inject `AnonymizerInterface` into application services and use
the core payload, document, batch, and NDJSON methods.

## Custom services

Register services with explicit IDs in tag attributes. IDs must be unique
within each tag and cannot replace built-in IDs.

```yaml
# config/services.yaml
services:
  App\Anonymizer\HashStrategy:
    tags:
      - { name: anonymizer.replacement_strategy, id: hash }
  App\Anonymizer\CsvCodec:
    tags:
      - { name: anonymizer.payload_codec, id: csv }
  App\Anonymizer\PhoneTransformation:
    tags:
      - { name: anonymizer.value_transformation, id: phone_format }
  App\Anonymizer\CustomerProfiles:
    tags: ['anonymizer.profile_provider']
  App\Anonymizer\Module:
    tags:
      - { name: anonymizer.extension, priority: 0 }
```

Each tagged service must implement its corresponding core interface. Extension
modules run after providers, strategies, codecs, and transformations have been
registered; Symfony orders extension tags by descending `priority`.

## Optional integrations

Each capability below needs its matching package. Enabling a capability
without installing that package fails during container setup.

Faker strategies:

```sh
composer require php-anonymizer/anonymizer-faker
```

```yaml
anonymizer:
  faker: { enabled: true, locale: en_US, seed: 1234 }
```

Fixed personas:

```sh
composer require php-anonymizer/anonymizer-personas
```

```yaml
anonymizer:
  personas: { enabled: true, locale: de }
```

Keyed pseudonyms and date shifting require an application service implementing
`PseudonymKeyProviderInterface` and the pseudonymization package:

```sh
composer require php-anonymizer/anonymizer-pseudonymization
```

```yaml
anonymizer:
  pseudonymization:
    enabled: true
    key_provider: App\Security\AnonymizerKeyProvider
```

The service returns key material for each non-secret profile `key_id`. Keep
keys in application-managed secret storage. See the
[pseudonymization guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-pseudonymization/docs/usage.md)
for contextual requirements and `date_shift` options.

Audit logging is disabled by default. Enabling it requires a PSR-3 logger
service:

```yaml
anonymizer:
  audit:
    enabled: true
    logger_service: monolog.logger.anonymizer
```

Object anonymization needs both the adapter package and an application-owned
Symfony Serializer service with normalizers and denormalizers configured:

```sh
composer require php-anonymizer/anonymizer-symfony-serializer
```

```yaml
anonymizer:
  serializer:
    enabled: true
    service: serializer
```

Use `serializer` as the service name when that is the app's configured
Serializer; otherwise set `service` to its ID. See the
[Serializer guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-symfony-serializer/docs/usage.md).

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md),
[migration guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/migration-v2.md),
and [legacy importer guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/legacy-rule-import.md).
