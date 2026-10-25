# Refined Iteration 33 — Mutation Gate Recovery

## Summary

Fresh Infection baseline: 2,074 killed, 262 escaped, and zero timed-out
mutants out of 2,336 (88.78% covered MSI). Restore the existing 90% gate
without public API changes, mutation suppressions, or loss of current user
worktree changes.

## Implementation changes

- Mark `AnonymizationReport` as covered—not merely used—in tests that directly
  exercise its disabled, collecting, and merge behavior, so Infection selects
  those tests for the ten escaped report mutants.
- Strengthen `AnonymizerBuilder` tests with sequential single registrations and
  duplicate-key generators containing distinct providers, strategies, codecs,
  and transformations. Assert every registration remains observable after
  building the façade.
- Extend the direct `ArrayAnonymizer` mutation suite with exact depth,
  collection-budget, visited-node, selected `children`/`each`, and unselected
  map/list boundary cases. Assert exact failure pointers and resulting output.
- Cover supplied transformation registries, caller-provided budgets, invalid
  document-list roots, and invalid list-item shapes through direct traversal
  tests.
- Simplify `ArrayTraversal`'s redundant list-item predicate to the meaningful
  map check, preserving validation behavior while removing an equivalent
  logical-or mutant.
- Add NDJSON string-key ordinal and distinct audit-key cases where valid input
  changes diagnostics or aggregation. Defer unreachable/internal-contract-only
  NDJSON mutants for later triage.

Keep defensive `Assert`-removal and semantically equivalent condition mutants
unsuppressed; do not add `@infection-ignore-next-line` annotations in this
iteration.

## Verification

- Run focused builder, report, traversal, document, and NDJSON PHPUnit suites
  after each batch.
- Run `PHP_VERSION=8.5 composer test`, `test:coverage`, `mutation`, `lint`,
  `static`, `refactor:check`, and finally `PHP_VERSION=8.5 composer qa`.
- Require zero timeouts/errors and at least 90% covered MSI (2,103 kills if
  the mutant total remains 2,336).
- Refresh Graphify after the production refactor; do not commit generated
  Graphify output.

No changelog update is required because the work is internal mutation
validation and preserves public behavior.

## Implementation status (2026-08-24)

- Added direct report coverage attribution, builder retention generators,
  traversal budget/depth fixtures, NDJSON string-key validation, and the
  redundant list-item predicate cleanup.
- Infection now reports 2,103 killed / 229 escaped / 0 timed out out of 2,332
  mutants (90% covered MSI).
- Full PHPUnit, published coverage (100% lines), lint, PHPStan, Rector, and
  Composer QA pass.
