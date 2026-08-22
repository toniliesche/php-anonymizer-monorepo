# PHP Anonymizer 2.0 API Surface

This is the semver boundary for the 2.0 release. Only symbols listed here are
supported public APIs. Concrete implementation classes remain Composer
autoloadable for technical reasons, but applications and extension packages
must not depend on them.

## Application façade and construction

The application-facing contract is one generic `AnonymizerInterface`. It
handles array and optional object operations, payload/document operations,
batch and NDJSON processing, audit output, and contextual execution. The
façade is constructed through the fluent `AnonymizerBuilder` introduced in
Iteration 19.

The builder accepts profiles or profile providers, replacement strategies,
payload codecs, value transformations, execution limits, extension modules,
and an optional object adapter. Direct construction of `ArrayAnonymizer` and
the current `AnonymizerFactory` helpers is not part of the 2.0 contract.

The builder is immutable and reusable. Registration methods return a new
builder, while `build()` returns an independent façade snapshot. The façade
keeps named array, payload, document, batch, NDJSON, report, and audit methods;
contextual execution is an optional `AnonymizationContext` argument. Object
calls that need adapter-specific options use `ObjectAnonymizationRequest`.

## Extension ports

The following ports are open for third-party implementations:

- `ProfileProviderInterface`
- `ReplacementStrategyInterface`
- `ContextualReplacementStrategyInterface`
- `PayloadCodecInterface`
- `DocumentPayloadCodecInterface`
- `ValueTransformationInterface`
- `ContextualValueTransformationInterface`
- `ObjectAnonymizerInterface` (new in Iteration 19)
- `AnonymizerExtensionInterface` (new in Iteration 19)
- `PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension` from
  the optional Serializer package
- `PseudonymKeyProviderInterface` from the optional pseudonymization package

Extension IDs are stable, case-sensitive identifiers. Implementations must
validate their options, avoid exposing source values or secrets in diagnostics,
and provide contract tests for success, invalid input, and failure behavior.
Symfony and Laravel integrations also support the `anonymizer.extension`
discovery tag for module implementations. Tagged modules are applied after
individual extension ports; Symfony orders them by descending `priority`,
while Laravel preserves registration order.

## Public values, results, and failures

The supported value and result contracts are:

- profile definitions, metadata, rule, condition, embedded-payload, scalar,
  and transformation values used to describe anonymization rules;
- profile inspection summaries and the profile-inspection interface;
- anonymization limits and contextual execution values;
- anonymization, payload, document, audit, and NDJSON result interfaces and
  immutable result values;
- documented report/audit entries;
- profile, payload, execution-limit, context, unsupported-value, and object
  normalization/denormalization exceptions.

Exact method signatures and return types are fixed by the Iteration 19 refined
plan. Internal report accumulators, JSON pointers, registries, and traversal
state are not public values.

## Framework entry points

The following framework integration entry points remain public:

- Symfony `AnonymizerBundle` registration and the legacy import command;
- Laravel `AnonymizerServiceProvider` auto-discovery;
- documented package Composer metadata and published framework configuration.

Container compiler passes, service registrars, YAML codecs, profile composers,
audit adapters, and Serializer dispatch classes are implementation details.

## Optional package factories

Plain-PHP users may use the documented Faker and Personas factory entry points
to obtain registered strategies. The strategy objects, catalogs, formatter
enums, locale/field enums, and persona data structures are internal; callers
use stable scalar IDs and locale strings.

## Migration boundary

The following v1 usages are transitional and have planned replacements:

| v1 usage | 2.0 replacement |
| --- | --- |
| `AnonymizerFactory::fromArrays()` / `fromProfiles()` / `fromProfileProviders()` | Fluent `AnonymizerBuilder` |
| `new ArrayAnonymizer(...)` | Builder-created generic façade |
| Separate batch/document/NDJSON/contextual service aliases | The generic façade |
| Direct `SymfonyAnonymizer` or `SymfonyObjectAnonymizer` usage | Object adapter registered with the builder/framework integration |
| Direct registry, traversal, policy, or compiler usage | No public replacement; use the façade or extension ports |
| Legacy importer collaborators | Legacy importer command only; collaborators are internal |

The complete symbol-by-symbol disposition is maintained in
`implement/iterations/18-public-surface-inventory.md`.
