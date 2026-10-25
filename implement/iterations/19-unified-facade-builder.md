# Iteration 19 — Unified Façade, Immutable Builder, and Extension Modules

## Status

Completed.

## Goal

Replace the fragmented capability-specific APIs and multi-argument
construction path with one 2.0 façade, an immutable fluent builder, and
explicit extension ports.

## Delivered

- Consolidated array, object, payload, document, records, NDJSON, report,
  audit, and contextual operations behind `AnonymizerInterface`.
- Added immutable `AnonymizerBuilder`, `ObjectAnonymizationRequest`,
  `ObjectAnonymizerInterface`, `AnonymizerExtensionInterface`, and the
  unsupported-object capability failure.
- Introduced the internal runtime and façade; made `ArrayAnonymizer` an
  internal engine and removed the legacy factory construction path.
- Updated Serializer, Symfony, Laravel, optional integrations, API docs,
  migration references, consumer fixtures, and affected changelogs.

## Acceptance

- Builder snapshots, registration ordering, duplicate detection, façade
  contracts, object adaptation, framework bindings, laziness, and contextual
  behavior are covered by tests and consumers.
- The clean 2.0 break has no compatibility aliases or deprecated factory
  shims.

The implementation specification is [Refined Iteration 19 — Unified façade,
immutable builder, and extension modules](../refined-iteration-19-unified-facade-builder-plan.md).
