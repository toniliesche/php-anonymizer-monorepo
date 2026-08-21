# Iteration 13 — Document Payloads and NDJSON Exports

## Goal

Add map/list document and lazy streaming payload APIs for large exports while
keeping the existing map-only payload methods and `AnonymizerInterface`
source-compatible.

## Deliverables

- Optional profile `root` (`map` by default or explicit `list`) and explicit
  validation of document root and list-item shapes.
- `DocumentPayloadCodecInterface`, document result types, and additive document
  and contextual document anonymizer interfaces; retain the legacy map codec
  contract and errors.
- Lazy `NdjsonAnonymizerInterface` and contextual counterpart for JSON-object
  records, with compact ordered output, per-record limit reset, bounded memory,
  and audit available only after successful exhaustion.
- Safe aggregate document/stream audit and Bundle aliases, codec registration,
  and optional completion-only audit logging. Keep CSV/XML and arbitrary object
  transformation out of this iteration.

## Acceptance tests

- Cover root parsing/defaults, invalid roots, map/list compatibility, malformed
  documents, nested/embedded paths, list indexes, transformations, contextual
  execution, and shared document limits.
- Cover NDJSON laziness, ordering, blank/malformed/non-object records,
  record-level limit reset, fail-on-pull behavior, bounded memory, wildcard
  audit aggregation, successful finalization, and incomplete/failed streams.
- Cover Bundle codec registration, aliases, conditional audit logging, and
  exclusion of raw values, secrets, replacements, payloads, and profiles from
  diagnostics and audit output.

## Handoff

Publish the document/NDJSON capability as `1.4.0` only after the full quality
gate, consumer checks, graph refresh, and annotated iteration/release checks
pass.
