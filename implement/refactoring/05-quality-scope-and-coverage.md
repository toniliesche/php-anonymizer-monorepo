# Slice 5: Quality Scope and Coverage Gates

## Objective

Make quality metrics cover all published code and ensure that branch coverage
is measured rather than reported as a vacuous `0/0` Clover metric.

## Changes

- Add Laravel and pseudonymization source directories to Infection. Keep the
  minimum covered MSI at 90%, remove the current timeout, and reject blanket
  mutation suppressions.
- Add a quality-scope tool that compares all package `src` directories with
  PHPUnit and Infection configuration, failing when a publishable package is
  omitted.
- Generate Cobertura coverage instead of Clover and update the coverage check
  to require non-zero, 100% line and branch metrics.
- Review every coverage exclusion. Keep only documented framework-wiring or
  unreachable defensive cases; add tests for behavior-relevant code instead.
- Expand Laravel Testbench, pseudonymization, serializer, and framework adapter
  tests before making their mutation scope mandatory.

## Acceptance

- All seven packages are covered by mutation testing at MSI >= 90%.
- Coverage reports real non-zero branches at 100% coverage.
- `composer check`, `composer test:coverage`, `composer mutation`, and
  `composer qa` all pass.
