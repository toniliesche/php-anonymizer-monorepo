# Laravel integration usage

Install `php-anonymizer/anonymizer-laravel` in a PHP 8.5+ Laravel 13
application. Composer installs the core package and Laravel auto-discovers the
service provider. Publish its configuration:

```sh
composer require php-anonymizer/anonymizer-laravel
php artisan vendor:publish --tag=anonymizer-config
```

The published `config/anonymizer.php` includes profiles, limits, Faker,
Personas, audit, pseudonymization, and Serializer settings. Optional features
are disabled by default. A basic profile and limits setup looks like this:

<!-- executable -->
```php
// config/anonymizer.php
return [
    'profiles' => [
        'customer' => [
            'mode' => 'preserve',
            'rules' => [
                ['field' => 'name'],
                ['field' => 'email', 'strategy' => 'star_mask'],
            ],
        ],
    ],
    'limits' => [
        'max_input_bytes' => 10_485_760,
        'max_depth' => 64,
        'max_visited_nodes' => 100_000,
        'max_collection_entries' => 50_000,
        'max_embedded_payload_depth' => 8,
    ],
    'faker' => ['enabled' => false, 'locale' => 'en_US', 'seed' => null],
    'personas' => ['enabled' => false, 'locale' => 'en'],
    'audit' => ['enabled' => false, 'logger_service' => null],
    'pseudonymization' => ['enabled' => false, 'key_provider' => null],
    'serializer' => ['enabled' => false, 'service' => null],
];
```

Resolve `PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`
from Laravel's container. It supports the core array, payload, document,
batch, and NDJSON methods. The integration does not provide a facade or
automatically connect HTTP responses, middleware, Eloquent models, or model
fields to profiles.

## Register application services

Configured profiles are composed first. Custom profile providers are added
after them; they must implement `ProfileProviderInterface` and return
`ProfileDefinition` values. Register providers, strategies, codecs,
transformations, and extension modules with the following tags (each service
must implement its corresponding core interface):

- `anonymizer.profile_provider`
- `anonymizer.replacement_strategy`
- `anonymizer.payload_codec`
- `anonymizer.value_transformation`
- `anonymizer.extension`

Tagged services are collected in Laravel container registration order.
Strategy, codec, and transformation IDs must be unique and cannot replace
built-in IDs. The YAML payload codec is registered automatically.

## Optional integrations

To enable Faker strategies, install the optional package and set
`faker.enabled` plus its locale and optional seed:

```sh
composer require php-anonymizer/anonymizer-faker
```

```php
'faker' => ['enabled' => true, 'locale' => 'en_US', 'seed' => 1234],
```

For fixed synthetic personas, install `php-anonymizer/anonymizer-personas`
and set `personas` to `['enabled' => true, 'locale' => 'de']`.

Keyed pseudonyms and `date_shift` need
`php-anonymizer/anonymizer-pseudonymization` and an application service that
implements `PseudonymKeyProviderInterface`:

```php
'pseudonymization' => [
    'enabled' => true,
    'key_provider' => App\Security\AnonymizerKeyProvider::class,
],
```

The provider's `keyFor(string $keyId): string` method supplies application-
managed key material. Keep secrets outside profiles and logs. See the
[pseudonymization guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-pseudonymization/docs/usage.md)
for contextual requirements and date-shift options.

Audit logging is disabled by default. To enable it, set `audit.enabled` and
`audit.logger_service` to a Laravel container service implementing PSR-3's
`LoggerInterface`.

Object anonymization is opt-in. Install
`php-anonymizer/anonymizer-symfony-serializer`, configure an application-owned
Symfony Serializer service implementing both normalizer and denormalizer
interfaces, and set `serializer.enabled` and (if needed) `serializer.service`.
When omitted, the provider resolves the
`Symfony\Component\Serializer\SerializerInterface` service. The
integration does not construct a Serializer; configure its normalizers for
your object types. See the
[Serializer guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-symfony-serializer/docs/usage.md).

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md)
and [migration guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/migration-v2.md).
