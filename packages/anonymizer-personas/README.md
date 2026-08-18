# PHP Anonymizer Personas

Fixed European placeholder personas for deterministic anonymization.

```sh
composer require php-anonymizer/anonymizer-personas
```

The package supports `en`, `de`, `es`, `fr`, `it`, `nl`, and `pt`, plus
specific European regional personas for `en_IE`, `de_AT`, `de_CH`, and
`nl_BE`. It also accepts matching regional forms such as `en_GB` and
`pt_PT`. It provides stable `persona.*`
strategies for names, contact details, and address fields. Emails use the
non-deliverable `example.invalid` domain. The base personas are respectively
British, German, Spanish, French, Italian, Dutch, and Portuguese; the
regional personas represent Ireland, Austria, Switzerland, and Belgian Dutch.
Conflicting regional locales such as `en_US` and `pt_BR` are rejected.
