# Refined Iteration 32 — Core Runtime Mutation Recovery

## Summary

Start from the fresh Infection baseline of 2,062 killed mutants out of
2,333 (88.38% covered MSI). Restore the 90% gate at 2,100 kills, then reach
the 93% milestone at 2,170 kills (at least 108 additional kills). Keep zero
timeouts, preserve public behavior and APIs, and add no mutation suppressions
in this iteration.

## Work packages

1. Strengthen `AnonymizerBuilder` tests with non-empty generators and distinct
   registrations for every bulk fluent API. Build the façade and prove each
   profile, provider, strategy, codec, transformation, limits object,
   extension, and object adapter affects observable behavior; retain
   immutability and invalid-provider coverage.
2. Expand `RuleTreeTraversal` and `ArrayTraversal` tests for selected
   `children`, `each`, string-list, and scalar-fallback dispatch; selected and
   unselected maps, empty maps, lists, scalar values; exact report paths;
   nested list indices; normalized-key paths; depth boundaries; and
   collection-budget boundaries, including the exact failing pointer.
3. Make reporting and NDJSON assertions structural: collecting versus disabled
   reports, merge behavior, path prefixing, event ordering, actions, nullable
   handlers, and NDJSON aggregation with multiple event keys, stable sorted
   audit output, leading whitespace, CRLF trimming, blank/non-string/non-object
   records, invalid JSON, and lazy audit completion.
4. Complete adversarial core-definition cases for conditions, profile
   composition, and profile/rule/options/metadata parsers, including null vs
   scalar vs list distinctions, absent and malformed paths, normalization,
   nested diagnostics, duplicate/conflicting rules, malformed declarations,
   unknown keys, incompatible combinations, and cycles. Assert exact rejection
   diagnostics.

Treat a survivor as a defect only when a valid public input demonstrates
changed behavior. Otherwise retain the defensive assertion and defer any
individually justified `@infection-ignore-next-line` decision to Iteration 3.

## Verification and checkpoints

- Run Infection first and re-triage survivors in the builder, traversal,
  report/NDJSON, condition, and composition clusters.
- Implement builder and report/NDJSON tests first; require at least 38 new
  kills and restore the enforced 90% gate.
- Implement traversal and execution-limit tests next; verify path, depth, and
  budget arithmetic mutants with focused suites.
- Implement parser/condition/composition boundary tests; require the aggregate
  93% threshold before closing the iteration.
- After each package, run focused PHPUnit followed by Infection. Reclassify
  equivalent or contract-only survivors instead of adding redundant tests.

## Acceptance

- Focused builder, runtime/traversal, document/NDJSON, condition-definition,
  and profile-composition suites pass.
- Full checks pass: `PHP_VERSION=8.5 composer test`, `composer test:coverage`,
  `composer mutation`, `composer lint`, `composer static`, and
  `composer refactor:check`.
- Published line coverage remains 100%; tests have no risky, skipped,
  incomplete, warning, or deprecated results; Infection has zero timeouts.
- With 2,333 total mutants, at least 2,170 are killed/resolved (93% covered
  MSI), with no new suppressions and no public API changes.
- Refresh Graphify after production-code changes.
