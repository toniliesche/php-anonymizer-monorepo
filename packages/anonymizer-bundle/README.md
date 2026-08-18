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
      rules:
        - field: email
          strategy: star_mask
```

The core anonymizer is available through the autowireable
`PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`.

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
must be unique and must not replace the built-in `redact`, `star_mask`, or
`json` IDs. Faker, YAML payloads, object serialization, and legacy
configuration are provided by later packages or iterations.
