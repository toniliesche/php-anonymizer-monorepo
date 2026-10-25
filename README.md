# PHP Anonymizer

This repository is the PHP Anonymizer 2.x development monorepo. Development
uses PHP 8.5+, Composer, and the root Composer scripts.

The current work-in-progress release is 2.0.0. Install the packages you need
with Composer; the Laravel integration is available for Laravel 13:

```sh
composer require php-anonymizer/anonymizer php-anonymizer/anonymizer-laravel
```

The core API is built around one façade and an immutable builder:

```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('customer', [
        'mode' => 'redact',
        'rules' => [['field' => 'email']],
    ]))
    ->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'user@example.com']);
// ['email' => '[REDACTED]']
```

Preserve profiles act as allow-lists. Selected strings remain unchanged while
other strings are redacted recursively:

```php
$profile = ProfileDefinition::fromArray('customer-safe', [
    'mode' => 'preserve',
    'rules' => [['field' => 'email']],
]);

$safe = $anonymizer->anonymize('customer-safe', [
    'email' => 'user@example.com',
    'name' => 'Ada',
]);
// ['email' => 'user@example.com', 'name' => '[REDACTED]']
```

The profile schema, conditions, and scalar fallbacks are documented in
[`docs/profile-schema.md`](docs/profile-schema.md).

See the complete [user guide](docs/user-guide.md) and
[2.0 migration guide](docs/migration-v2.md) for application setup and upgrade
instructions.

The core also supports named string strategies and encoded payloads:

```php
$profile = ProfileDefinition::fromArray('payload', [
    'mode' => 'redact',
    'rules' => [['field' => 'email', 'strategy' => 'star_mask']],
]);

$json = $anonymizer->anonymizePayload('payload', 'json', '{"email":"Ada"}');
// {"email":"***"}
```

Custom strategies and codecs implement the framework-free interfaces described
in [`docs/extension-author-guide.md`](docs/extension-author-guide.md).

Array consumers can process records lazily without retaining prior records or
reports:

```php
foreach ($anonymizer->anonymizeRecords('customer', $records) as $safe) {
    export($safe);
}
```

The optional `php-anonymizer/anonymizer-pseudonymization` package provides
keyed deterministic pseudonyms. Register an application key provider, use a
leaf rule with `strategy: pseudonymize` and a non-secret `key_id`, then pass an
`AnonymizationContext` with per-request scope attributes to the façade call.

Execution limits are enabled by default. Configure `AnonymizationLimits` with
`AnonymizerBuilder::withLimits()`. Encoded payloads are
limited to 10 MiB by default, with additional bounds for traversal depth,
visited nodes, collection entries, and embedded payload depth.

Callers that need safe operational telemetry can use the façade's
`anonymizeWithAudit()` method. Audit entries contain profile identity, a
compiled profile fingerprint, paths, actions, handler IDs, and counts—never
source or replacement values.

Symfony applications can install `php-anonymizer/anonymizer-bundle` and
configure the same profiles under `anonymizer.profiles`. The Bundle exposes an
autowireable `AnonymizerInterface` and discovers custom providers, strategies,
and codecs through explicit `anonymizer.*` service tags. See the Bundle
README for installation and Flex/manual registration details.

Laravel 13 applications can install `php-anonymizer/anonymizer-laravel`. Its
service provider is auto-discovered, composes configured profiles with ordered
`anonymizer.*` tags, and supports the standalone Symfony Serializer adapter
when explicitly enabled with an application-owned Serializer service.

Reusable profile libraries can expose immutable `ProfileDefinition` objects
through `ProfileProviderInterface`. Register providers explicitly with
`AnonymizerBuilder::withProfileProviders()` (or Laravel's
`anonymizer.profile_provider` tag); providers are never scanned or discovered
implicitly.

Run `composer qa` for the complete quality gate.

Maintainers can follow the [release and publishing guide](docs/release-and-publishing.md)
for repository setup, CI credentials, package registration, and release recovery.

For performance checks, run `PHP_VERSION=8.5 composer benchmark`. The isolated
benchmark uses two warmups, seven samples, and 100 iterations per scenario;
`BENCHMARK_WARMUPS`, `BENCHMARK_SAMPLES`, and `BENCHMARK_ITERATIONS` can tune
local runs. CI compares the semantic output digest strictly and reports
warnings above 10% median time or 15% peak memory; these warnings are
non-blocking because shared runners are noisy.
