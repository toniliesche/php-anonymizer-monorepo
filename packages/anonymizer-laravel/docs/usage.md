# Laravel integration usage

Install `php-anonymizer/anonymizer-laravel` with Laravel 13 and publish its
configuration. The auto-discovered provider composes configured profiles with
services tagged `anonymizer.profile_provider`,
`anonymizer.replacement_strategy`, `anonymizer.payload_codec`,
`anonymizer.value_transformation`, or `anonymizer.extension` in registration
order.

Serializer support is opt-in and requires an application-owned Symfony
Serializer service. The package does not provide a facade, Eloquent mapping,
middleware, response mutation, or automatic model mapping.

See the repository [`user guide`](../../../docs/user-guide.md) and
[`migration guide`](../../../docs/migration-v2.md).

After publishing the configuration, a typical profile and integration setup is:

<!-- executable -->
```php
// config/anonymizer.php
return [
    'profiles' => [
        'customer' => [
            'mode' => 'preserve',
            'rules' => [
                ['field' => 'name'],
                ['field' => 'email', 'strategy' => 'persona.email'],
            ],
        ],
    ],
    'limits' => ['max_input_bytes' => 1_048_576, 'max_depth' => 32],
    'personas' => ['enabled' => true, 'locale' => 'de'],
    'audit' => ['enabled' => false, 'logger_service' => null],
];
```

Resolve `AnonymizerInterface` from the container and call
`anonymize('customer', $data)` or any of the payload, document, batch, and
NDJSON methods. Register custom profile providers and extensions with the
documented tags; the provider does not add a facade or mutate responses.
