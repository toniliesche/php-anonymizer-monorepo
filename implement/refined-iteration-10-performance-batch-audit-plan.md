# Refined Iteration 10 Implementation Plan — Performance, Limits, Batches, and Auditability

## Goal

Deliver the first post-1.0 production-safety improvements in the core
anonymizer. Preserve `AnonymizerInterface` and existing transformations while
adding bounded execution, lazy batch processing, and safe aggregate audit data.

The roadmap discussed separate performance/limits and batch/auditability
slices, but that split never became separate iterations. There was no distinct
Iteration 10.1 or 10.2 milestone: all of this scope shipped in one
`iteration-10` commit, released as `1.1.0`.

## Traversal and performance

- Add a disabled-report traversal mode so `anonymize()` does not allocate or
  retain changed/missing paths while producing the same transformed data as the
  report API.
- Keep condition behavior unchanged while snapshotting only the current map's
  condition inputs and evaluating each rule/map condition once before applying
  transformations.
- Remove the JSON codec's second full decode. Inspect the JSON root before the
  single associative decode so object-root validation and `{}`/`[]`
  distinction remain unchanged.

## Execution limits

- Add immutable `AnonymizationLimits` with defaults of 10 MiB input bytes,
  depth 64, 100,000 visited nodes, 50,000 collection entries, and embedded
  payload depth 8; reject non-positive values.
- Add a per-operation `ExecutionBudget` for input size, depth, visited nodes,
  collection size, and nested embedded-payload depth. Share the budget across
  embedded traversal and reset it for every batch record.
- Raise a typed limit exception containing the profile, configured limit,
  observed value where applicable, and current JSON Pointer; include codec
  context for encoded-payload failures.
- Expose limits through optional Bundle configuration under
  `anonymizer.limits`, overriding core defaults.

## Batch API and audit API

- Add `BatchAnonymizerInterface::anonymizeRecords(string $profile,
  iterable $records): iterable` without changing the existing interface.
- Accept only associative array records, yield lazily, retain no prior record
  or report, and surface invalid input or transformation failures when the
  corresponding record is pulled. Include the record ordinal in validation
  failures where available.
- Add array-only `AuditAnonymizerInterface::anonymizeWithAudit()` returning
  transformed data and immutable aggregate entries.
- Aggregate by profile, deterministic compiled-profile SHA-256 fingerprint,
  JSON Pointer, action, and nullable strategy/codec handler. Sort entries
  deterministically and store counts only; never store source values,
  replacements, payloads, or raw profile definitions.
- Keep batch output-only in this iteration. Contextual batch/audit methods are
  added later by Iteration 11.

## Bundle integration and compatibility

- Always expose the batch alias when the Bundle is configured.
- Add disabled-by-default audit configuration with a configured PSR-3 logger;
  when enabled, adapt one aggregate audit result to one `info` record and
  expose the audit alias only while enabled.
- Keep PSR logging in the Bundle and keep the core package framework-free.
- Preserve existing profile semantics, reports, embedded payload paths, object
  integration, and source compatibility of the original anonymizer facade.

## Tests and acceptance criteria

- Cover no-report equivalence, report suppression, memoized conditions, JSON
  decoding behavior, all exact and exceeded limits, embedded-depth restoration,
  pointer-rich failures, and Bundle limit wiring.
- Cover lazy batch evaluation, independent record budgets, associative-map
  validation, ordinal/error behavior, and fail-on-pull semantics.
- Cover audit aggregation, deterministic ordering, fingerprint stability,
  handler attribution, unknown profiles, immutable result accessors, and
  serialized audit data free of source/replacement values.
- Cover conditional Bundle audit aliases, logger configuration, and safe log
  contexts. Run the normal Composer quality gate and the documented benchmark
  job separately from the deterministic PR gate.

## Reconstruction basis

This plan reconstructs the delivered changes from the single
`iteration-10` / `1.1.0` tag, the post-Iteration-9 roadmap,
`docs/profile-schema.md`, and the Iteration 10 unit and Bundle tests. It records
delivered behavior and compatibility constraints; no separate 10.1 or 10.2
plan or release exists, and streaming document batches remain deferred to
later roadmap iterations.
