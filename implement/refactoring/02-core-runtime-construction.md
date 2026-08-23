# Slice 2: Core Runtime Construction

## Objective

Make builder-produced runtime assembly the single primary construction path and
leave the internal engine focused on executing an already validated runtime.

## Changes

- Extract built-in strategy/codec/transformation registration, iterable
  materialization, registry creation, and profile-reference validation from
  `ArrayAnonymizer` into an internal runtime assembler.
- Keep the flow `AnonymizerBuilder -> AnonymizerRuntime -> AnonymizerFacade /
  ArrayAnonymizer`; the engine receives a validated runtime rather than
  independently assembling collaborators.
- Retain the existing direct `ArrayAnonymizer` constructor as an internal,
  transitional forwarding path. Preserve its defaults, validation timing,
  exceptions, and iterable-key semantics; document it as deprecated/internal
  rather than removing it.
- Keep builder immutability and reusable snapshots. Materialize supplied
  iterables exactly once when registered, not during operation execution.

## Tests and acceptance

- Cover keyed iterables, duplicate/built-in collisions, validation timing,
  default registrations, generator materialization, and independent builder
  snapshots.
- Characterization tests produce identical transformed data, reports, and
  exceptions through direct and builder-produced runtimes.
- No public API manifest change is required; `composer qa` passes.
