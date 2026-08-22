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
NDJSON, report, audit, contextual, and optional object operations. Capability
interfaces and framework aliases that previously exposed separate services are
not part of the 2.0 boundary.

`ArrayAnonymizer`, registries, traversal services, profile compilers, and
framework registrars remain Composer-autoloadable implementation details only.
Applications and extension packages must use the API listed in
[`api-surface-v2.md`](api-surface-v2.md).

## Symfony Serializer migration

Replace direct `SymfonyAnonymizer` or `SymfonyObjectAnonymizer` construction
with the `SymfonySerializerExtension` or a registered
`SymfonyObjectAnonymizerAdapter` supplied by the application-owned Symfony
Serializer. The adapter returns a new object and rejects `object_to_populate`.
The Bundle and Laravel integration expose the same generic façade; Laravel
Serializer support remains explicitly opt-in.

## Framework configuration

Keep configured profiles and existing individual extension tags. Add coherent
modules with `anonymizer.extension`; Symfony applies them by descending
priority and Laravel by registration order. Bind the documented
`AnonymizerInterface`, not internal engines or capability-specific aliases.

## Legacy importer

The Symfony `anonymizer:import-legacy-rules` command remains a one-way migration
aid in 2.x. Its converters, readers, renderers, and writers are internal, and
the command is deprecated for removal in 3.0. Review generated configuration
and manually migrate unsupported object-access or custom-provider constructs.
See [`legacy-rule-import.md`](legacy-rule-import.md) for command details.

## Composer and rollout

Update all PHP Anonymizer packages to the `^2.0` constraint together. Run the
consumer tests and inspect reports, audit output, and exception paths for
source-value disclosure before deployment. Existing profiles do not need a
schema migration unless they use a removed construction or framework binding.
