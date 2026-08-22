# Iteration 20 — Core and Framework Cohesion

## Status

Completed.

## Goal

Split the core execution pipeline, profile processing, and framework wiring
into focused internal collaborators while preserving supported anonymization
behavior and removing cognitive-complexity suppressions.

## Delivered

- Added the internal traversal execution context and separated rule indexing,
  structural traversal, and selected-value dispatch.
- Extracted focused services for array, payload, document, records, NDJSON,
  audit, profile compilation, validation, and reference-cycle checks.
- Split Symfony and Laravel wiring into focused internal registrars while
  preserving configuration keys, defaults, tags, diagnostics, and lazy
  operations.
- Removed obsolete capability-specific dispatch and direct engine bindings.
- Removed the scoped cognitive-complexity suppressions and marked extracted
  implementations internal.

## Acceptance

- Traversal precedence, conditions, normalized keys, payloads, limits, report
  paths, profile diagnostics, NDJSON exhaustion, audit ordering, and framework
  integration behavior remain covered and unchanged.
- Published code contains no cognitive-complexity suppression.

The implementation specification is [Refined Iteration 20 — Core and
Framework Cohesion](../refined-iteration-20-core-framework-cohesion-plan.md).
