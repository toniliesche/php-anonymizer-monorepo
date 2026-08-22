# User guide

PHP Anonymizer turns profile definitions into a reusable, immutable façade.
Start with the core package for arrays, then add only the integrations your
application needs.

## Install and build

```sh
composer require php-anonymizer/anonymizer
```

The builder is framework-independent and can be reused to create independent
facades:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]);
$anonymizer = AnonymizerBuilder::create()->withProfile($profile)->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'user@example.test']);

assert($safe === ['email' => '[REDACTED]']);
```

Builders are immutable. `withProfile()`, `withProfileProvider()`, strategy,
codec, transformation, limit, object-adapter, and extension methods return a
new builder; `build()` materializes a reusable façade.

## Profiles and traversal

Profiles use `redact` mode by default in which selected strings are replaced.
`preserve` mode is an allow-list: selected strings remain unchanged and other
strings are redacted. Use `children` for maps and `each` for lists. Conditions
read original sibling values and use strict `equals` or `in` comparisons.

The complete vocabulary, precedence rules, fragments, global rules, key
normalization, and error behavior are documented in
[`profile-schema.md`](profile-schema.md).

### A profile library (rulesets)

Keep related profiles in a provider when several applications or bounded
contexts share the same rules. Providers are explicit; the builder never scans
the container or filesystem for profiles.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final class CustomerProfiles implements ProfileProviderInterface
{
    public function profiles(): iterable
    {
        yield ProfileDefinition::fromArray('customer.public', [
            'mode' => 'preserve',
            'rules' => [['field' => 'name'], ['field' => 'email', 'strategy' => 'star_mask']],
        ]);
        yield ProfileDefinition::fromArray('customer.internal', [
            'mode' => 'redact',
            'rules' => [['field' => 'email'], ['field' => 'phone']],
        ]);
    }
}

$anonymizer = AnonymizerBuilder::create()->withProfileProvider(new CustomerProfiles())->build();
$safe = $anonymizer->anonymize('customer.public', [
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.test',
    'note' => 'private',
]);

if ($safe !== ['name' => 'Ada Lovelace', 'email' => '****************', 'note' => '[REDACTED]']) {
    throw new RuntimeException('The profile provider example did not produce the expected result.');
}
```

### Nested records, conditions, and typed values

One profile can combine structural traversal, conditional leaves, list rules,
and typed transformations. The input shape remains unchanged.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('orders', [
    'mode' => 'redact',
    'rules' => [
        ['field' => 'customer', 'children' => [['field' => 'email', 'strategy' => 'star_mask']]],
        ['field' => 'orders', 'each' => [['field' => 'reference'], ['field' => 'total', 'transform' => ['type' => 'numeric_bucket', 'options' => ['size' => 100]]]]],
        ['field' => 'phone', 'when' => ['path' => 'region', 'equals' => 'EU']],
    ],
]);
$result = AnonymizerBuilder::create()->withProfile($profile)->build()->anonymize('orders', [
    'region' => 'EU',
    'customer' => ['email' => 'ada@example.test'],
    'phone' => '+44 20 0000 0000',
    'orders' => [['reference' => 'A-42', 'total' => 245]],
]);

if ($result['customer']['email'] !== '****************' || $result['phone'] !== '[REDACTED]' || $result['orders'][0]['reference'] !== '[REDACTED]') {
    throw new RuntimeException('The nested profile example did not produce the expected result.');
}
```

## Strategies and typed transformations

String leaves can select a registered strategy such as `star_mask`, Faker,
Personas, or an application strategy. Typed leaves use `transform` instead of
`strategy`; built-ins include `nullify`, `partial_mask`, `date_generalize`, and
`numeric_bucket`. Strategies and transformations are mutually exclusive with
structural traversal and embedded payloads.

Extensions implement the public ports documented in the
[`extension author guide`](extension-author-guide.md). Register related ports
through an `AnonymizerExtensionInterface` module when that is more convenient.

## Payloads, documents, and embedded data

`anonymizePayload()` remains map-only. `anonymizeDocument()` supports an
explicit map or list root, and `anonymizeNdjson()` processes logical JSON lines
lazily. Embedded JSON or YAML rules decode a selected string, apply another
profile, and encode it again. Codec and profile failures retain the operation,
profile, codec, and path without exposing payload contents.

### JSON payloads and embedded payloads

Use `anonymizePayload()` for an encoded map. An embedded rule applies a second
profile to a JSON string stored inside the outer document.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$outer = ProfileDefinition::fromArray('event', [
    'mode' => 'redact',
    'rules' => [['field' => 'metadata', 'embedded' => ['codec' => 'json', 'profile' => 'metadata']]],
]);
$metadata = ProfileDefinition::fromArray('metadata', [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]);
$anonymizer = AnonymizerBuilder::create()->withProfiles([$outer, $metadata])->build();
$json = $anonymizer->anonymizePayload('event', 'json', json_encode([
    'metadata' => json_encode(['email' => 'ada@example.test'], JSON_THROW_ON_ERROR),
], JSON_THROW_ON_ERROR));

if ($json !== '{"metadata":"{\\"email\\":\\"[REDACTED]\\"}"}') {
    throw new RuntimeException('The embedded payload example did not produce the expected JSON.');
}
```

## Context, pseudonyms, and safety

Install `php-anonymizer/anonymizer-pseudonymization` for keyed HMAC pseudonyms.
Register an application-owned `PseudonymKeyProviderInterface`, use a non-secret
`key_id`, and pass an `AnonymizationContext` for tenant or scope attributes.
Keys never belong in profiles, reports, exceptions, or logs.

Execution limits are enabled by default. Configure `AnonymizationLimits` with
`withLimits()` for payload bytes, traversal depth, visited nodes, collection
entries, and embedded depth. Limit failures identify the profile and JSON
Pointer.

Use `anonymizeWithReport()` for paths and actions, or
`anonymizeWithAudit()` for aggregate safe telemetry. Audit output contains
profile identity, a behavior fingerprint, paths, handler IDs, and counts—not
source or replacement values.

## Batches and streams

`anonymizeRecords()` accepts an iterable and yields one map at a time. It does
not retain previous records or reports and fails when the failing record is
pulled. NDJSON results expose lazy `lines()` and only expose aggregate `audit()`
after successful exhaustion.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('records', [
    'mode' => 'redact',
    'root' => 'list',
    'rules' => [['field' => 'email']],
]);
$anonymizer = AnonymizerBuilder::create()->withProfile($profile)->build();
$batch = iterator_to_array($anonymizer->anonymizeRecords('records', [
    ['email' => 'one@example.test'],
    ['email' => 'two@example.test'],
]), false);
$stream = $anonymizer->anonymizeNdjson('records', [
    '{"email":"one@example.test"}',
    '{"email":"two@example.test"}',
]);
$lines = iterator_to_array($stream->lines(), false);

if ($batch !== [['email' => '[REDACTED]'], ['email' => '[REDACTED]']] || $lines !== ['{"email":"[REDACTED]"}', '{"email":"[REDACTED]"}']) {
    throw new RuntimeException('The batch or NDJSON example did not produce the expected result.');
}
if ($stream->audit()->entries()[0]->path() !== '/*/email') {
    throw new RuntimeException('The NDJSON audit example did not aggregate stream paths.');
}
```

## Symfony objects

Install `php-anonymizer/anonymizer-symfony-serializer` and provide the
application-owned Symfony Serializer to `SymfonySerializerExtension`. Objects
are normalized, anonymized, and denormalized into a new instance; the source
object is never populated or mutated. The Symfony Bundle wires this extension
and the common profile/codec/strategy tags for you.

## Symfony Bundle and Laravel

The Bundle reads profiles from `anonymizer.profiles`, supports explicit
extension tags, and orders `anonymizer.extension` modules by descending
priority. Its YAML codec, optional Faker/Personas/Pseudonymization integrations,
limits, and audit logger are configured under `anonymizer`.

The Laravel package is auto-discovered. Publish its configuration, provide
profiles under `anonymizer.profiles`, and register application or package
extensions with the documented tags. Laravel preserves tag registration order.
Serializer support is opt-in and always uses an application-owned Serializer;
the package does not provide facades, Eloquent mapping, middleware, or response
mutation.

For the clean-break changes from the former API, see
[`migration-v2.md`](migration-v2.md). Package archives contain focused local
guides for their specific integration.
