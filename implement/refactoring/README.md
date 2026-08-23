# Architecture-led Refactoring Programme

## Goals

- Preserve current behavior, error messages, output/report ordering, public
  configuration, and documented public APIs.
- Improve internal boundaries, test signal, and performance visibility without
  adding a material application runtime or memory cost.
- Evolve caller-visible APIs only additively and with deprecations. This
  programme removes no public API.

## Delivery order

1. [Public contract and characterization coverage](01-public-contract-and-characterization.md)
2. [Core runtime construction](02-core-runtime-construction.md)
3. [Framework composition roots](03-framework-composition-roots.md)
4. [Compiled traversal rule selection](04-compiled-traversal-rule-selection.md)
5. [Quality-scope and coverage gates](05-quality-scope-and-coverage.md)
6. [Performance observability](06-performance-observability.md)
7. [Release, documentation, and closeout](07-release-documentation-and-closeout.md)

Each slice is independently reviewable and releasable. Run focused checks
first; run `PHP_VERSION=8.5 composer qa` before merging a completed slice.

## Shared constraints

- The API manifest defines the supported semver surface. Every other
  production declaration is explicitly `@internal` but remains autoloadable.
- Symfony and Laravel stay separate integrations. Shared behavior is protected
  by contract tests, not a new framework abstraction or package.
- All seven published packages participate in coverage and mutation quality
  checks.
- Performance timing is trend-based initially; laziness and resource-bound
  invariants remain deterministic PR checks.
