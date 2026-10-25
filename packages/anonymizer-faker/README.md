# PHP Anonymizer Faker

### Project Status

[![Tests](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml/badge.svg)](https://github.com/toniliesche/php-anonymizer-monorepo/actions/workflows/quality.yml)
[![Code Coverage](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo/branch/develop/graph/badge.svg)](https://codecov.io/gh/toniliesche/php-anonymizer-monorepo)
[![Downloads](https://img.shields.io/packagist/dt/php-anonymizer/anonymizer-faker.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-faker)
[![Stars](https://img.shields.io/github/stars/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/stargazers)

### Project Info

[![PHP Version](https://img.shields.io/packagist/php-v/php-anonymizer/anonymizer-faker)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Latest Version](https://img.shields.io/packagist/v/php-anonymizer/anonymizer-faker.svg)](https://packagist.org/packages/php-anonymizer/anonymizer-faker)
[![License](https://img.shields.io/github/license/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE)

### Repo Status

[![Last Commit](https://img.shields.io/github/last-commit/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo)
[![Issues](https://img.shields.io/github/issues/toniliesche/php-anonymizer-monorepo)](https://github.com/toniliesche/php-anonymizer-monorepo/issues)

### License

This project is released under the MIT license.

See the [LICENSE](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/LICENSE) for more information.

The package provides Faker-backed replacement strategies for common personal
and address fields. It requires PHP 8.5+, the core package, and FakerPHP; all
are installed by Composer:

```sh
composer require php-anonymizer/anonymizer-faker
```

Use `FakerReplacementStrategyFactory::create($locale, $seed)` and pass the
resulting strategies to the core anonymizer. The locale follows FakerPHP's
locale support. A seed makes values reproducible for the same FakerPHP version
and call sequence; it does not promise identical output across dependency
versions. IDs are `faker.building_number`, `faker.city`, `faker.company`,
`faker.country`, `faker.email`, `faker.first_name`, `faker.house_number`,
`faker.last_name`, `faker.name`, `faker.password`, `faker.postcode`,
`faker.street`, `faker.street_number`, `faker.username`, and `faker.zip`.
`faker.postcode` and `faker.zip` use the same Faker formatter.

See the package-local [usage guide](docs/usage.md).
