# PHP Anonymizer Pseudonymization

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftoniliesche%2Fphp-anonymizer-monorepo%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/toniliesche/php-anonymizer-monorepo/main)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-pseudonymization.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-pseudonymization)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-pseudonymization)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-pseudonymization.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-pseudonymization)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

Keyed deterministic pseudonymization and date shifting for PHP Anonymizer 2.0.
Requires PHP 8.5+ and the core package. The public API currently exposes
`PseudonymKeyProviderInterface`, but not a standalone strategy or
transformation registration API. Use the optional Bundle or Laravel
integration to register internal implementations and provide an
application-owned key provider. Do not instantiate `HmacPseudonymStrategy`;
it is internal and may change without notice.

The registered `pseudonymize` strategy uses a non-secret profile `key_id` and
requires contextual anonymization. `date_shift` requires contextual
anonymization, an integer `max_days` from 1 to 36525, and a `scope_attribute`
whose string value is present in context. Both use application-managed key
material; secret values must never be placed in profiles or logs.

See the package-local [usage guide](docs/usage.md).
