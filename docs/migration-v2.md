# Migrating to PHP Anonymizer 2.0

Version 2.0 is a clean break. Runtime compatibility aliases are not provided;
move callers to the documented façade, builder, and extension ports.

## Construction

Replace factory and concrete-engine construction:

| 1.x usage | 2.0 usage |
| --- | --- |
| `AnonymizerFactory::fromArrays()` / `fromProfiles()` / `fromProfileProviders()` | `AnonymizerBuilder::create()` with `withProfiles()` or `withProfileProviders()` |
| `new ArrayAnonymizer(...)` | `AnonymizerBuilder::create()->...->build()` |
| Separate batch, document, NDJSON, audit, or contextual services | The configured `AnonymizerInterface` façade |
| Direct registry, compiler, traversal, or provider-collection usage | No public replacement; use the façade or documented extension port |

Builders are immutable and materialize iterables during registration. Keep the
builder if an application needs several independent façade snapshots.

## Unified façade

The public `AnonymizerInterface` now owns array, payload, document, batch,
NDJSON, report, audit, contextual, and optional object operations. Old
per-operation framework service aliases are not part of the 2.0 boundary; the
documented extension ports remain supported.

`ArrayAnonymizer`, registries, traversal services, profile compilers, and
framework registrars remain Composer-autoloadable implementation details only.
Applications and extension packages must use the API listed in
[`api-surface-v2.md`](api-surface-v2.md).

## Symfony Serializer migration

Replace direct `SymfonyAnonymizer` or `SymfonyObjectAnonymizer` construction
with the public `SymfonySerializerExtension` and an application-owned Symfony
Serializer. The adapter creates a new object by default and passes
`ObjectAnonymizationRequest::adapterOptions()` to normalization and
denormalization. Omit `object_to_populate` when source immutability matters.
Do not construct the internal `SymfonyObjectAnonymizerAdapter` directly. The
Bundle and Laravel integration expose the same generic façade; Laravel
Serializer support remains explicitly opt-in.

## Framework configuration

Keep configured profiles and existing individual extension tags. Add coherent
modules with `anonymizer.extension`; Symfony applies them by descending
priority and Laravel by registration order. Bind the documented
`AnonymizerInterface`, not internal engines or capability-specific aliases.

## Legacy importer

The Symfony `anonymizer:import-legacy-rules` command remains a one-way migration
aid in 2.x. Its converters, readers, renderers, and writers are internal.
Review generated configuration and manually migrate unsupported object access
or custom-provider behavior.
See [`legacy-rule-import.md`](legacy-rule-import.md) for command details.

## Composer and rollout

Update all PHP Anonymizer packages to the `^2.0` constraint together. Validate
existing profiles against the current schema and update removed constructions
or framework bindings. Exercise the consuming application's profiles and
inspect reports, audit output, and exception paths for source-value disclosure
before deployment.
