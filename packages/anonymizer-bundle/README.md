# PHP Anonymizer Symfony Bundle

Install `php-anonymizer/anonymizer-bundle` alongside the core package. Symfony
Flex can register the bundle; manual registration uses
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

Profile providers use `anonymizer.profile_provider`. Strategy and codec IDs
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
