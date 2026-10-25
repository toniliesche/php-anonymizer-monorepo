# PHP Anonymizer Faker

The package provides framework-free Faker replacement strategies for common
personal and address fields. Install it with the core package:

```sh
composer require php-anonymizer/anonymizer-faker
```

Use `FakerReplacementStrategyFactory::create('en_US', 1234)` and pass the
resulting strategies to the core anonymizer. IDs use the `faker.*` snake-case
format, for example `faker.first_name` and `faker.email`.

See the package-local [usage guide](docs/usage.md).
