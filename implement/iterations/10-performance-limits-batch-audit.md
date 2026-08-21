# Iteration 10 — Performance Limits, Batch Processing, and Safe Auditability

## Goal

Deliver the first post-1.0 production-safety release while preserving the
existing array anonymizer contract and profile behavior. The performance and
batch/audit roadmap slices ship together as one `iteration-10` / `1.1.0`
delivery.

## Deliverables

- No-report traversal for output-only anonymization, memoized condition inputs,
  and single-decode JSON handling without changing error semantics.
- Immutable `AnonymizationLimits` and shared per-operation `ExecutionBudget`
  covering payload bytes, depth, visited nodes, collection size, and embedded
  payload depth; expose overrides through optional Bundle configuration.
- Additive lazy `BatchAnonymizerInterface` for associative map records, with
  independent budgets and fail-on-pull validation.
- Array-only `AuditAnonymizerInterface` returning deterministic aggregate
  entries containing counts and safe profile/path/action/handler metadata only.
- Optional Bundle audit logging through one PSR-3 record, disabled by default;
  keep logging and framework dependencies out of the core package.

## Acceptance tests

- Verify output equivalence with and without reports, condition memoization,
  JSON root handling, every exact/exceeded limit, embedded-budget sharing, and
  contextual pointer-rich failures.
- Verify lazy batch iteration, associative-record validation, ordinal-aware
  failures, independent record limits, bounded retention, and fail-on-pull
  behavior.
- Verify deterministic audit grouping/order/fingerprints, handler attribution,
  immutable accessors, and exclusion of source values, replacements, payloads,
  secrets, and raw profiles.
- Verify conditional Bundle aliases, logger configuration, and safe serialized
  log contexts. Run the normal quality gate and the separate benchmark job.

## Handoff

Publish `1.1.0` only after the deterministic quality gate, consumer checks, and
the pinned-runner performance comparison pass.
