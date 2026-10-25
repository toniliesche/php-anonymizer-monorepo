# PHP Anonymizer

This repository contains the clean v1 rewrite of PHP Anonymizer. Development
uses PHP 8.5+, Composer, and the root Composer scripts.

The first core API is intentionally small and array-only:

```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerFactory;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$anonymizer = AnonymizerFactory::fromArrays(['customer' => [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]]);
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
leaf rule with `strategy: pseudonymize` and a non-secret `key_id`, then call
the contextual anonymizer with per-request scope attributes.

Execution limits are enabled by default. `AnonymizationLimits` can be passed to
`AnonymizerFactory::fromArrays()` as its final argument. Encoded payloads are
limited to 10 MiB by default, with additional bounds for traversal depth,
visited nodes, collection entries, and embedded payload depth.

Array-only callers that need safe operational telemetry can use the optional
`AuditAnonymizerInterface`. Audit entries contain profile identity, a compiled
profile fingerprint, paths, actions, handler IDs, and counts—never source or
replacement values.

Symfony applications can install `php-anonymizer/anonymizer-bundle` and
configure the same profiles under `anonymizer.profiles`. The Bundle exposes an
autowireable `AnonymizerInterface` and discovers custom providers, strategies,
and codecs through explicit `anonymizer.*` service tags. See the Bundle
README for installation and Flex/manual registration details.

Run `composer qa` for the complete quality gate.
