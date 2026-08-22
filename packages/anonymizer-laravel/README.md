# PHP Anonymizer Laravel Integration

Install the 2.0.0 work-in-progress package in Laravel 13 applications:

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

Object anonymization is opt-in. Enable `anonymizer.serializer.enabled` and
provide an application-owned Symfony Serializer service through
`anonymizer.serializer.service`, or bind
`Symfony\\Component\\Serializer\\SerializerInterface`. The integration never
constructs a Serializer and does not require the Symfony Bundle.

The package has no facade and does not provide Eloquent, middleware, response,
or automatic model-to-profile integration.

See the package-local [usage guide](docs/usage.md).
