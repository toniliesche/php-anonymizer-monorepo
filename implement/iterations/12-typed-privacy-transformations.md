# Iteration 12 — Typed Privacy Transformations

## Goal

Add additive leaf-only transformations for typed privacy operations while
retaining existing string strategy behavior and all existing anonymizer
interfaces.

## Deliverables

- `transform` rule maps with mutually exclusive `strategy`, `transform`, and
  `embedded` actions; validate supported input types, output types, and options.
- Core transformation port, contextual transformation port, and validated
  registry with duplicate/unknown-ID diagnostics.
- Built-ins for `nullify`, UTF-8 `partial_mask`, `date_generalize`, and
  `numeric_bucket`, with type-preserving date and numeric behavior.
- Optional contextual `date_shift` in the pseudonymization package, plus
  Serializer object/context integration and capability-dependent Bundle tags
  and aliases.

## Acceptance tests

- Cover profile validation, registry failures, transformation defaults and
  boundaries, Unicode masking, numeric subtype preservation, timezone/DST and
  RFC 3339 handling, preserve mode, conditions, nested/`each` rules, embedded
  payloads, reports, and safe audit output.
- Cover deterministic date shifting, key rotation, scope/path separation,
  missing context/key failures, serializer immutability, and Bundle aliases.
- Ensure failures identify profile/path/transform/options or types without
  exposing source values, raw dates, secrets, or replacements.

## Handoff

Publish the typed transformation capability as the next minor release only
after array, payload, object, Bundle, consumer, and full QA checks pass.
