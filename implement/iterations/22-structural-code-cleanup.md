# Iteration 22 — Structural Code Cleanup and PSR-4 Compliance

## Goal

Improve structural consistency across production and testing code without
changing anonymization logic, output, error contracts, or established public
application/domain namespaces. Renaming and moving internal classes and test
fixtures is in scope.

The current user-guide, migration, and release-closure work moves to Iteration
23 unchanged.

## Deliverables

- A complete PSR-4 audit and correction for all repository-owned production and
  test PHP files.
- No anonymous classes in production or testing code; all test doubles become
  named fixture implementations in matching `Tests\Support` namespaces.
- Deferred callback arguments replaced by named interfaces and implementations,
  while small inline callbacks passed to `array_*` functions remain allowed.
- Internal namespace and folder cleanup organized by role without changing
  established public application/domain namespaces.
- A reviewed `@var` inventory with safe replacements using native narrowing,
  existing domain guards, or Webmozart Assert where the existing contract is
  already preserved.
- A structural convention check that prevents regressions.

## Structural and namespace rules

- Every named class, interface, trait, and enum lives in its own file whose
  path exactly matches its PSR-4 namespace and symbol name.
- Extract secondary production declarations such as `RuleIndex` into matching
  files.
- Move embedded test fixtures such as serializer subjects, provider services,
  and framework test doubles into package-local `Tests\Support` namespaces.
- Keep package Composer prefixes and established public API namespaces stable;
  only internal role-oriented namespaces and test namespaces may be renamed.
- Preserve `final`, `readonly`, visibility, and `#[Override]` semantics unless
  the change is required to make a named fixture or moved class valid.

## Callback and anonymous-code cleanup

- Replace anonymous test implementations with named reusable fixtures that
  implement the existing extension interfaces.
- Replace closures supplied as constructor or service-registration arguments
  with small internal interfaces and concrete implementations.
- Preserve lazy NDJSON audit behavior through a named audit-provider contract;
  audit must remain unavailable until successful stream exhaustion.
- Keep concise callbacks used directly by `array_map`, `array_filter`,
  `array_any`, and equivalent collection functions.
- Keep only framework-required Laravel/Symfony registration or configuration
  closures as documented allowlisted exceptions because those APIs require
  `Closure` values for deferred resolution.

## Annotation and style audit

- Remove redundant `@var` annotations where native control flow already gives
  the required type.
- Retain array-shape, generic, and iterable annotations that PHP cannot express
  in native type declarations.
- Use Webmozart Assert only after existing validation has established the same
  invalid-input behavior; do not replace domain exceptions or alter messages
  merely to improve static narrowing.
- Add `webmozart/assert` directly only to packages that import it in production
  code.
- Apply configured formatter, import, docblock, and safe Rector improvements;
  do not change business logic or remove suppressions owned by other
  iterations.

## Verification

- Add structural tests for PSR-4 path matching, one declaration per file,
  anonymous-class rejection, deferred-closure rejection, allowed collection
  callbacks, and framework callback exceptions.
- Update coverage metadata and imports for moved test fixtures.
- Preserve regression coverage for NDJSON audit timing, serializer failures,
  Laravel bindings, extension registration, and all existing anonymization
  behavior.
- Run `PHP_VERSION=8.5 composer lint`, `static`, `refactor:check`, `test`,
  `test:coverage`, and `qa`.
- Refresh Graphify with `graphify update .` after implementation and keep
  generated Graphify output uncommitted.

## Acceptance criteria

- No repository-owned anonymous classes remain under production or test code.
- Every repository-owned named symbol is PSR-4 compliant and isolated in its
  matching file.
- No non-allowlisted deferred callback is passed as a function argument.
- Existing public behavior, output, exceptions, lazy evaluation, and supported
  namespaces remain unchanged.
- Composer metadata, static analysis, formatting, tests, coverage, mutation,
  consumer checks, and architecture validation pass.

## Assumptions

- “No anonymous code” means anonymous classes everywhere in repository-owned
  production and test PHP.
- Closure cleanup targets closures passed as deferred callback arguments;
  compact `array_*` callbacks remain intentionally permitted.
- Laravel and Symfony framework callback seams are explicit, tested exceptions,
  not a reason to redesign framework resolution in this structural iteration.
