# Iteration 28 — 2.0 Release Readiness and Performance Baseline

## Status

Implemented and accepted. The full PHP 8.5 QA gate passes, including release
fixtures, benchmark comparison, consumer suites, mutation testing, and package
artifact generation.

## Summary

Close the refactoring programme around the existing `2.0.0` release train.
Keep runtime behavior and public PHP APIs unchanged; improve release
verification, document the current coverage-driver limitation, and make
benchmark regressions visible on relevant pull requests without blocking them.

## Release and documentation

- Centralize the seven-package 2.0 release-train metadata used by release
  readiness and artifact construction, removing duplicated package and
  documentation lists.
- Strengthen release readiness to verify package names, `2.0.x-dev` aliases,
  `^2.0` internal constraints, required package documentation, and correctly
  ordered `Unreleased` / `2.0.0` changelog headings.
- Add fixture-based release-readiness tests and run them from `composer qa`;
  retain CI-only release verification and do not create a release branch or
  release tag.
- Update the root README with the benchmark command, runtime variables, and
  interpretation policy.
- Correct contradictory root changelog wording: coverage is strict positive
  100% line coverage; branch/path coverage remains deferred for PHP
  8.5.9/Xdebug 3.5.3.
- Mark the performance and closeout roadmap slices with their implementation
  evidence. Leave package changelogs unchanged because this iteration has no
  caller-visible package behavior change.

## Warning-only benchmark regression check

- Commit a canonical PHP 8.5 GitHub Actions benchmark baseline containing the
  four current scenarios, iteration/sample configuration, median time, peak
  memory, and semantic digest. Capture it from the Ubuntu runner using the
  existing benchmark command; do not hand-edit measurements.
- Add a strict benchmark comparison tool that:
  - rejects malformed or stale baseline data, changed scenario names/configuration,
    missing metrics, or changed output digests;
  - treats a median-time increase above 10% or peak-memory increase above 15%
    as a GitHub Actions warning;
  - exits successfully for threshold regressions, but fails for invalid
    comparison inputs or semantic-output drift.
- Extend the benchmark workflow to run on pull requests affecting package
  source, benchmark tooling/baseline, or Composer dependency metadata, as well
  as the existing schedule and manual dispatch.
- Upload the raw result artifact on every run and emit warnings into the
  workflow log/summary. Benchmark instrumentation remains CI/development-only
  and must add no application runtime dependency or hot-path work.

## Test plan

- Add benchmark-comparison fixtures for valid equal results, time-only
  regression, memory-only regression, digest drift, missing/unknown scenarios,
  and malformed baseline data.
- Cover release-readiness success plus each enforced metadata, documentation,
  and changelog failure.
- Run focused tool fixtures, documentation examples,
  `PHP_VERSION=8.5 composer check`,
  `PHP_VERSION=8.5 composer test:coverage`, and
  `PHP_VERSION=8.5 composer qa`; refresh Graphify after implementation.
- Confirm benchmark warnings are non-blocking while invalid benchmark
  contracts fail the benchmark workflow.

## Assumptions

- `develop` targets synchronized `2.0.0`; the supplied `1.7.0` instruction is
  superseded by the checked-out release metadata and roadmap.
- The current line-only coverage gate remains intentional until a stable,
  compatible branch/path coverage driver is available.
- Benchmark thresholds are investigation signals only; updating the checked-in
  baseline requires intentional review with an explanation in the iteration
  record.
