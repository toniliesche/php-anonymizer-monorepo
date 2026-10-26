# PHP Anonymizer Symfony Serializer

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftoniliesche%2Fphp-anonymizer-monorepo%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/toniliesche/php-anonymizer-monorepo/main)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-symfony-serializer.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-symfony-serializer)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-symfony-serializer)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-symfony-serializer.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-symfony-serializer)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

The adapter requires PHP 8.5+, the core package, and `symfony/serializer`
8.1.9 or later in the 8.1 line. It adds object support to the core
`AnonymizerInterface`: arrays use the core engine, while objects are normalized,
anonymized, then denormalized into a new instance through an application-owned
Symfony Serializer implementing both normalizer and denormalizer interfaces.
The adapter creates a new instance by default. Symfony Serializer context
options are forwarded as supplied, so avoid passing an `object_to_populate`
target when source immutability matters. Serializer support comes from this
optional adapter; the core remains framework-free.

See the package-local [usage guide](docs/usage.md).
