# Slice 7: Release, Documentation, and Closeout

## Objective

Complete each slice with durable documentation, release hygiene, and full
verification rather than accumulating untracked cleanup work.

## Procedure

- Update the root and affected package changelogs for caller-visible changes
  or deprecations; keep the current `Unreleased` section at the top.
- Update API, migration, extension, and framework documentation plus executable
  examples whenever the supported surface or migration path changes.
- Record the completed implementation details, acceptance evidence, and any
  approved performance baseline change in the corresponding slice document.
- Run focused tests first, then `PHP_VERSION=8.5 composer qa`, relevant
  consumer checks, the benchmark/invariant suite, and `graphify update .`.

## Completion criteria

- The API manifest, documentation, examples, and release notes agree.
- Full QA and affected package consumer tests pass.
- Benchmark artifacts show no unexplained material regression; approved
  baseline changes are documented with their cause.
