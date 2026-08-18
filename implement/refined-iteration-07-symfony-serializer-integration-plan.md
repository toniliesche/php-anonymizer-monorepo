# Refined Iteration 7 — Symfony Serializer Object Integration

## Summary

Ship `php-anonymizer/anonymizer-symfony-serializer` as the immutable object
adapter and make it a required part of `php-anonymizer/anonymizer-bundle`.
The core remains array-only; objects are normalized, anonymized through the
existing core API, then denormalized into a fresh object.

## Public API and architecture

- Add an internal `SymfonyObjectAnonymizer` and a unified facade implementing
  the existing core `AnonymizerInterface`; do not add a second public
  anonymizer interface. Its constructor accepts the core
  `AnonymizerInterface` plus an application-provided
  `NormalizerInterface&DenormalizerInterface`.
- Provide:

  ```php
  anonymize(string $profile, object $object, array $context = []): object
  anonymizeWithReport(string $profile, object $object, array $context = []): ObjectAnonymizationResult
  ```

- `ObjectAnonymizationResult` exposes the new object and the existing
  `AnonymizationResult`; PHPDoc templates preserve the source object type for
  static analysis.
- Pass one caller-provided context unchanged to both `normalize()` and
  `denormalize()`. Reject `object_to_populate` so callers cannot accidentally
  mutate the source object.
- Normalize to an associative array only, anonymize via
  `anonymizeWithReport()`, and denormalize to the exact source class. Reject
  scalar/list normalized values and a denormalized value of another class.
- Add phase-specific normalization and denormalization exceptions with
  profile, source class, and the original throwable as `previous`. Core
  anonymization failures remain unchanged.

## Bundle and dependencies

- The Serializer package directly requires `php-anonymizer/anonymizer` and
  `symfony/serializer` for Symfony 8.
- The Bundle directly requires the Serializer adapter and always registers
  `SymfonyObjectAnonymizer`; alias the existing `AnonymizerInterface` publicly
  for autowiring. No `anonymizer.serializer.enabled` setting is added.
- Add a compiler-time, actionable failure when the application has no
  `serializer` service, explaining that the Bundle uses the application-
  provided Symfony Serializer and how to enable/register it.
- Update the Bundle README and recipe with constructor-injection examples for
  the unified `AnonymizerInterface`, its immutable-object contract, required
  Serializer service, and context use.

## Verification and delivery

- Unit-test source immutability, nested-object anonymization, same-class
  output, report propagation, context propagation to both Serializer
  operations, unsupported normalized shapes, wrong-class output, and wrapped
  conversion exceptions.
- Add Bundle integration coverage using a real Symfony Serializer service,
  verify autowiring of the object adapter, and cover the missing-serializer
  compile failure.
- Add an isolated Composer consumer test and Make/Composer target for the
  Serializer package.
- Extend PHPUnit source coverage, Infection, PHPStan paths, Deptrac’s
  Serializer-integration layer, collision detection, package metadata
  validation, root development dependencies, and release artifacts for the
  new package.
- Record the Iteration 7 roadmap entry and update CHANGELOG and package
  READMEs when implementation begins.

## Assumptions

- Symfony 8 and PHP 8.5 remain the supported platforms.
- The adapter never constructs or configures a Serializer; consumers own
  normalizers, metadata, name conversion, and serialization context.
- No reflection-based object traversal, mutable “populate existing object”
  mode, Bundle configuration toggle, or changes to the array-oriented core
  are introduced.
