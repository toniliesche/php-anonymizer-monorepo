# PHP Anonymizer Symfony Bundle

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-bundle.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-bundle)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-bundle)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-bundle.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-bundle)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

Install `php-anonymizer/anonymizer-bundle`; Composer installs the core and
required Symfony component dependencies. Symfony Flex can register the bundle;
manual registration uses
`PhpAnonymizer\AnonymizerBundle\AnonymizerBundle`.

```yaml
# config/packages/anonymizer.yaml
anonymizer:
  profiles:
    customer:
      mode: redact
      key_normalization: true
      rules:
        - field: email
          strategy: star_mask
      global_rules:
        - field: email_address
      metadata:
        owner: privacy-platform
        status: active
      fragments:
        - name: contact
          rules:
            - field: phone
        - name: identity
          rules:
            - use: contact
            - field: customer_id
```

The core anonymizer is available through the autowireable
`PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`.
Profile key normalization is comparison-only, and global rules apply within
the configured profile at every map depth without renaming dataset keys.
Fragments are profile-local ordered lists expanded during container compilation.
The first fragment rule wins a fragment collision; direct profile rules replace
fragment rules selecting the same field. The public
`ProfileInspectorInterface` exposes only safe profile summaries and behavior
fingerprints.

Custom extension services use explicit IDs on their tags:

```yaml
App\Anonymizer\HashStrategy:
  tags:
    - { name: anonymizer.replacement_strategy, id: hash }
App\Anonymizer\CsvCodec:
  tags:
    - { name: anonymizer.payload_codec, id: csv }
```

Profile providers use `anonymizer.profile_provider`; configured profiles are
consumed first, followed by tagged providers in tag order. Strategy and codec IDs
must be unique and must not replace built-in IDs. YAML payloads are available
as `yaml` automatically. Faker strategies are optional:

```sh
composer require php-anonymizer/anonymizer-faker
```

```yaml
anonymizer:
  faker: { enabled: true, locale: en_US, seed: 1234 }
```

Fixed European personas are also optional:

```sh
composer require php-anonymizer/anonymizer-personas
```

```yaml
anonymizer:
  personas: { enabled: true, locale: de }
```

The Bundle accepts execution limits under `anonymizer.limits`. Audit logging is
optional and disabled by default; enable it with a configured PSR-3 logger:

```yaml
anonymizer:
  audit:
    enabled: true
    logger_service: monolog.logger.anonymizer
```

Pseudonymization is optional; install `php-anonymizer/anonymizer-pseudonymization`
and configure its key provider as described in the
[usage guide](docs/usage.md). Object anonymization is also opt-in and requires
`php-anonymizer/anonymizer-symfony-serializer` in addition to the application-
owned Symfony Serializer service. Enable `anonymizer.serializer.enabled` and
provide that service through `anonymizer.serializer.service`, or use the
conventional `serializer` service.

See the package-local [usage guide](docs/usage.md).
