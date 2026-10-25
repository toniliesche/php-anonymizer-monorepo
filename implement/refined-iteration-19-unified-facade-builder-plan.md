# Refined Iteration 19 — Unified façade, immutable builder, and extension modules

## Objective

Replace the fragmented v1 construction and capability APIs with one 2.0
application façade, an immutable fluent builder, and explicit extension ports.
This is a clean breaking change; no compatibility aliases or deprecated factory
shims are retained.

Iteration 19 owns the public contract and the first implementation of the new
construction path. Complexity extraction, complete internal annotations, and
the extensive guides remain assigned to Iterations 20–22.

## Public contract

### Unified façade

`AnonymizerInterface` becomes the only anonymization service contract. It keeps
named, ergonomic operations for arrays, payloads, documents, records, NDJSON,
reports, and audit output. Contextual behavior is represented by an optional
`?AnonymizationContext $context = null` argument on the relevant methods; the
separate `AuditAnonymizerInterface`, `BatchAnonymizerInterface`, document,
NDJSON, and contextual capability interfaces are removed.

The array/object methods retain these shapes:

```php
anonymize(string $profile, array|object $data, ?AnonymizationContext $context = null): array|object
anonymizeWithReport(string $profile, array|object $data, ?AnonymizationContext $context = null): AnonymizationResultInterface
```

Payload and document methods keep their profile/codec/payload arguments and add
an optional context. Batch and NDJSON methods keep lazy iterable behavior and
also accept an optional context. Audit remains array-only because the existing
audit result and logger contracts have no object-audit semantics.

Add the following object-specific public values and port:

- `ObjectAnonymizationRequest` is immutable and contains the profile, source
  object, `AnonymizationContext`, and adapter-specific options.
- `ObjectAnonymizerInterface::anonymize(ObjectAnonymizationRequest $request,
  AnonymizerInterface $anonymizer): AnonymizationResultInterface` receives the
  request and delegates normalized associative data through the façade.
- `anonymizeObject()` and `anonymizeObjectWithReport()` expose that request
  path. A façade without an adapter throws the public
  `ObjectAnonymizationUnavailable` exception.

Object adapters must not mutate the source object and must return an object of
the source runtime class. Adapter options are opaque to core and documented by
the adapter package.

### Immutable builder

Add `AnonymizerBuilder`. Every registration method returns a new builder; the
original remains reusable, and each `build()` creates an independent immutable
façade snapshot. Iterables supplied to registration methods are materialized
when registered so generator-backed configuration cannot be consumed twice.

The builder provides singular and iterable convenience registration methods
for:

- `ProfileDefinition` values and `ProfileProviderInterface` instances;
- replacement strategies, payload/document codecs, and value transformations;
- `AnonymizationLimits`;
- one optional `ObjectAnonymizerInterface`;
- `AnonymizerExtensionInterface` modules.

`AnonymizerExtensionInterface::configure(AnonymizerBuilder $builder):
AnonymizerBuilder` expands a module immediately at `withExtension()` call time.
Registration order is preserved. Duplicate profile, extension, strategy,
codec, transformation, or object-adapter registrations fail; modules cannot
override earlier registrations or core built-ins.

Core defaults remain automatic: JSON payload support, the built-in redaction
and star-mask strategies, and core transformations are registered before
user/module extensions and therefore cannot be replaced.

### Internal construction

Introduce an internal runtime/configuration value that contains the validated
profile, strategy, codec, transformation, and limit registries. The builder
constructs this value once; `ArrayAnonymizer` receives the runtime as one
internal dependency instead of the current five-argument constructor.

Add an internal `AnonymizerFacade` that delegates array operations to the
internal engine and object operations to the optional object adapter. Remove
`AnonymizerFactory`; direct `ArrayAnonymizer` construction and registry access
are internal implementation details.

## Package and framework work

- Core removes the factory and capability interfaces, adds the builder,
  request, object port, unavailable exception, and façade implementation.
- The Serializer package keeps normalization/denormalization collaborators
  internal, implements `ObjectAnonymizerInterface`, and exposes a public
  `SymfonySerializerExtension` for plain-PHP builder registration. Existing
  serializer failure exceptions remain the adapter’s documented failures.
- Symfony Bundle and Laravel register the complete façade through their
  builder wiring. Only `AnonymizerInterface` and profile inspection remain
  public container bindings; concrete-engine and capability aliases are
  removed. Extension-module service tags are added for framework discovery.
- Audit adapters depend on the unified façade and call its array audit method.
- Update API-surface documentation, migration references, README examples,
  consumer fixtures, and affected package changelogs. Do not write the full
  User Guide or Extension Guide until their assigned iterations.

## Tests and acceptance

- Builder tests verify immutable snapshots, iterable materialization, ordered
  module expansion, duplicate detection, built-in collisions, limits, and
  single-adapter enforcement.
- Façade contract tests cover every existing array, payload, document, batch,
  NDJSON, audit, report, and contextual behavior through the one interface,
  including laziness and result types.
- Object tests cover missing adapters, request options, context forwarding,
  same-class enforcement, and Serializer normalization/denormalization errors.
- Symfony and Laravel integration tests verify façade-only public bindings and
  tagged module registration. Consumer fixtures must not instantiate factories,
  engines, registries, or capability interfaces.
- Run focused tests, `PHP_VERSION=8.5 composer test`, coverage, static checks,
  consumer tests, `PHP_VERSION=8.5 composer qa`, release-preview checks, and
  `graphify update .`. Generated Graphify output remains uncommitted.

## Fixed decisions

- The façade uses ergonomic named methods; request objects are introduced only
  where object adapter options require them.
- The builder is immutable and reusable.
- Extension modules are ordered and additive; no overrides are supported.
- Framework containers expose the façade only, not transitional capability or
  concrete adapter aliases.
- All packages remain on the synchronized 2.0.0 release train.
