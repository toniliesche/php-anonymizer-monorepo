# Iteration 29: Runtime assertions at untyped boundaries

## Status

Implemented and accepted on 2026-08-23. PHP 8.5 QA passed, including 100%
published-package line coverage, mutation testing, consumers, and artifacts.

## Objective

Reduce unsafe `@var` type claims by adding executable type validation where
values enter the library as `mixed`, framework container results, parser
results, or untyped extension iterables. Preserve existing domain-specific
exceptions, error messages, output ordering, and anonymization performance.

The source packages currently contain 85 `@var` occurrences. Most are valid
generic-array or value-object shape documentation and are not candidates for
Webmozart Assert because PHP has no native generic array syntax.

## Implementation scope

### Dependency and static-analysis setup

- Add `webmozart/assert:^2.4` only to published packages that execute it:
  `anonymizer`, `anonymizer-faker`, `anonymizer-pseudonymization`, and
  `anonymizer-laravel`.
- Add `phpstan/phpstan-webmozart-assert:^2.0` to the workspace development
  dependencies and ensure the PHPStan extension is loaded.
- Do not add the dependency merely to remove documentation annotations; every
  use must protect an actual runtime boundary or invariant.

### Core extension boundaries

- Assert interface membership for every item consumed by
  `ProfileRegistry`, `ReplacementStrategyRegistry`, `PayloadCodecRegistry`,
  and `TransformationRegistry` before invoking `id()` or other methods.
- Review `AnonymizerBuilder` bulk iterable methods and `RuntimeAssembler` so
  extension values are validated without eagerly materializing one-shot
  generators more than once.
- Keep registry and builder failure behavior as `InvalidArgumentException`
  with actionable messages.

### Narrow invariant assertions

- Replace the Faker seed `unpack()` shape annotation with an integer/key
  assertion.
- Replace DateShift’s validated `scope_attribute`, `max_days`, and hash
  `unpack()` annotations with assertions.
- Keep these checks outside the per-node traversal hot path.

### Laravel integration boundaries

- Assert that resolved pseudonym key providers implement
  `PseudonymKeyProviderInterface`.
- Assert that resolved audit services implement `LoggerInterface`.
- Validate tagged strategies, codecs, transformations, and extensions lazily
  per item so framework generators are not consumed or duplicated.
- Validate configured profile names/definitions before passing them into typed
  core APIs.
- Replace serializer intersection `@var` annotations with the existing native
  `NormalizerInterface&DenormalizerInterface` return type after its explicit
  runtime guard.

## Deliberate exclusions

- Keep property and constructor annotations for generic arrays and lists.
- Keep parser annotations where an explicit guard must throw
  `InvalidProfileDefinition`, `LegacyImportException`, `JsonException`,
  `ParseException`, or Symfony configuration exceptions.
- Do not replace stricter string-key checks with `Assert::isMap()` where its
  associative-array type claim would still permit integer keys.
- Do not introduce assertions into anonymization traversal loops or other
  per-record/per-node hot paths.

## Tests and acceptance criteria

- Add invalid-entry cases for every core registry and builder bulk path.
- Add deterministic Faker and DateShift regression coverage for asserted
  `unpack()` results.
- Add Laravel integration cases for invalid container services, tagged values,
  and profile configuration.
- Run, in order: `PHP_VERSION=8.5 composer lint`, `static`, `test`,
  `test:coverage`, `refactor:check`, and the full `PHP_VERSION=8.5 composer qa`.
- Require published-package line coverage to remain 100%, no new risky or
  skipped tests, and no benchmark regression beyond the existing thresholds.
- Run `graphify update .` after implementation; do not commit generated graph
  caches.

## Compatibility and performance assumptions

- Existing public/domain exception classes and messages remain authoritative.
  Assertions are used directly only where `InvalidArgumentException` is
  already the contract; other boundaries translate assertion failures.
- Validation happens at construction, registration, or framework boot time,
  not during ordinary anonymization traversal.
- The first implementation remains additive: no public API removal or
  serialization-format change.
