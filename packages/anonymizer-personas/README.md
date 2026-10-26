# PHP Anonymizer Personas

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftoniliesche%2Fphp-anonymizer-monorepo%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/toniliesche/php-anonymizer-monorepo/main)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-personas.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-personas)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-personas)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-personas.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-personas)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

Fixed European placeholder personas for deterministic anonymization. Requires
PHP 8.5+ and the core package, which Composer installs automatically.

```sh
composer require php-anonymizer/anonymizer-personas
```

`PersonaReplacementStrategyFactory::create($locale)` provides stable IDs
`persona.name`, `persona.first_name`, `persona.last_name`, `persona.email`,
`persona.username`, `persona.company`, `persona.phone`, `persona.street`,
`persona.street_number`, `persona.city`, `persona.postcode`, and
`persona.country`. Supported base locales are `en`, `de`, `es`, `fr`, `it`,
`nl`, and `pt` (British, German, Spanish, French, Italian, Dutch, and
Portuguese personas). Regional catalogs are `en_IE`, `de_AT`, `de_CH`, and
`nl_BE`; matching base-region forms such as `en_GB`, `de_DE`, `nl_NL`, and
`pt_PT` are accepted. Locale input is trimmed and case-insensitive, and
hyphens are accepted in place of underscores. Other regional combinations,
such as `en_US` and `pt_BR`, are rejected. Values are fixed placeholders, not
real-world identity records; email addresses use the non-deliverable
`example.invalid` domain.

See the package-local [usage guide](docs/usage.md).
