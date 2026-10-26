# PHP Anonymizer Laravel Integration

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftoniliesche%2Fphp-anonymizer-monorepo%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/toniliesche/php-anonymizer-monorepo/main)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-laravel.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-laravel)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-laravel)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-laravel.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-laravel)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

Install the package in a Laravel 13 application:

```sh
composer require php-anonymizer/anonymizer-laravel
```

Laravel auto-discovers the service provider and publishes configuration with:

```sh
php artisan vendor:publish --tag=anonymizer-config
```

Configured profiles are composed first, followed by services tagged in Laravel’s
registration order with `anonymizer.profile_provider`,
`anonymizer.replacement_strategy`, `anonymizer.payload_codec`, or
`anonymizer.value_transformation`.

Profile providers must be registered explicitly by the application or an
installed package. They return immutable profile definitions; providers do not
expose raw source data, secrets, or replacement values, and profiles are not
overridden or inherited.

The package binds the core anonymization services, profile inspection, audit,
batch, document, and NDJSON interfaces. Configure execution limits and optional
audit logging under `anonymizer.limits` and `anonymizer.audit`.

Object anonymization is opt-in and requires
`php-anonymizer/anonymizer-symfony-serializer` plus an application-owned
Symfony Serializer implementing normalization and denormalization. Enable
`anonymizer.serializer.enabled` and set `anonymizer.serializer.service` to its
container ID, or bind `Symfony\Component\Serializer\SerializerInterface`.
The integration never constructs a Serializer and does not require the Symfony
Bundle. See the package-local [usage guide](docs/usage.md) for optional package
installation and configuration.

The package has no facade and does not provide Eloquent, middleware, response,
or automatic model-to-profile integration.

See the package-local [usage guide](docs/usage.md).
