# Iteration 24: Core Runtime Construction

## Summary

Move runtime assembly out of `ArrayAnonymizer` so the builder creates one
validated, fully wired internal runtime graph. Preserve all behavior and keep
direct `ArrayAnonymizer` construction as an internal compatibility path.

## Implementation changes

- Add an internal `RuntimeAssembler` responsible for:
  - materializing registration iterables as values (discarding keys);
  - registering built-in JSON codecs, replacement strategies, and
    transformations exactly once per runtime;
  - constructing profile, codec, strategy, and transformation registries;
  - validating profile references and embedded-profile cycles;
  - constructing traversal, payload, and NDJSON collaborators.
- Change `AnonymizerRuntime` from raw configuration storage into the assembled,
  validated internal runtime graph. It exposes only the internal collaborators
  required by the array adapter.
- Update `AnonymizerBuilder::build()` to collect direct and provider profiles,
  then invoke the assembler. Builder methods remain immutable; supplied
  iterables are materialized once at registration and every `build()` returns
  an independent runtime snapshot.
- Reduce `ArrayAnonymizer` to an internal execution adapter:
  - accept an assembled `AnonymizerRuntime` and delegate its existing
    operations;
  - retain its existing direct constructor parameters, forwarding them through
    `RuntimeAssembler`;
  - preserve defaults, iterable-key behavior, exception classes/messages, and
    construction-time validation.
- Keep `AnonymizerFacade` and `AnonymizerInterface` signatures unchanged. The
  façade receives the preassembled runtime; no per-operation runtime lookup,
  extra allocation, or lazy configuration processing is introduced.
- Update the root and core-package changelogs under `Unreleased`; document the
  completed iteration in the refactoring roadmap. The executable public API
  manifest remains unchanged.

## Tests and acceptance

- Characterize direct and builder-produced runtimes as equivalent for array,
  report, audit, payload, document, batch, and NDJSON operations.
- Assert identical construction-time failures for duplicate registrations,
  built-in ID collisions, unknown strategy/codec/transformation/profile
  references, and embedded-profile cycles.
- Cover keyed iterables, generators, provider aggregation, and independent
  builder snapshots.
- Verify default registrations remain available and direct construction remains
  usable by existing tests.
- Ensure no configuration iterable is consumed during an anonymization
  operation; assembly occurs only during construction/build.
- Run focused core tests, then `PHP_VERSION=8.5 composer test`,
  `test:coverage`, `public-api`, `static`, `refactor:check`, `qa`, and
  `graphify update .`.

## Assumptions

- `ArrayAnonymizer`, `AnonymizerRuntime`, and `RuntimeAssembler` remain internal
  and autoloadable; there is no public API addition or removal.
- Compatibility means preserving current direct-constructor behavior,
  including when validation happens, rather than introducing earlier builder
  validation.
- Performance work in this iteration is structural: construction happens once,
  and operation-time behavior stays allocation- and dispatch-neutral. Formal
  benchmarks and regression thresholds remain part of the later
  performance-observability iteration.
