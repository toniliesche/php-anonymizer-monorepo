# Refined Iteration 22 — Structural Cleanup and PSR-4 Compliance

## Intent

Remove structural ambiguity while preserving anonymization behavior, output,
exceptions, and existing suppression decisions. Public 2.0 entry-point
namespaces remain canonical; internal and test-only names may move without
compatibility aliases.

## Implementation

- Keep every named declaration in its own PSR-4 file; extract `RuleIndex` and
  move serializer, framework, and extension fixtures into package-local
  `Tests\\Support` namespaces.
- Replace the core NDJSON audit closure with a named provider interface and
  deferred implementation, retaining the same lazy-completion failure.
- Reuse configurable named fixtures by extension contract. Keep only distinct
  throwing, contextual, or stateful fixtures where behavior requires it.
- Rename historical iteration tests by subject and move transformation tests
  below the transformation namespace. Keep framework registration closures and
  direct collection callbacks as explicit allowlisted seams.
- Add a PHP-parser-based Composer structural check for PSR-4 paths, anonymous
  classes, and non-allowlisted deferred closures.
- Remove only redundant `@var` annotations. Retain generic/shape annotations;
  use Webmozart Assert only where it improves narrowing without changing domain
  exceptions or messages.

## Acceptance

- `composer structure` reports no PSR-4 or anonymous-class violations.
- No repository-owned production/test anonymous classes remain.
- `composer check`, coverage, mutation, consumer tests, and `composer qa`
  pass without blanket suppression removal or logic changes.
