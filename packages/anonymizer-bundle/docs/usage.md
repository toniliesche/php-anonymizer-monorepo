# Symfony Bundle usage

Install the Bundle with the core and Serializer packages, register the bundle,
and configure profiles under `anonymizer.profiles`. The Bundle exposes one
autowireable `AnonymizerInterface` and supports explicit provider, strategy,
codec, transformation, and `anonymizer.extension` tags.

`anonymizer.extension` modules run after individual registrations and are
ordered by descending `priority`. Limits, audit logging, YAML payloads, Faker,
Personas, and keyed pseudonyms are optional configuration capabilities.

See the repository [`user guide`](../../../docs/user-guide.md),
[`migration guide`](../../../docs/migration-v2.md), and
[`legacy importer guide`](../../../docs/legacy-rule-import.md).

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
    records:
      root: list
      rules:
        - field: email
  limits:
    max_input_bytes: 1048576
    max_depth: 32
  faker:
    enabled: true
    locale: en_US
    seed: 1234
  audit:
    enabled: true
    logger_service: monolog.logger.anonymizer
```

Inject `AnonymizerInterface` into an application service and use the same
payload, document, batch, and NDJSON methods described in the root guide. The
Bundle also provides the `yaml` codec for map and list documents.
