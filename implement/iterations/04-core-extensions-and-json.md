# Iteration 04 — Core Extensions and JSON

## Goal

Turn the core into a stable platform without coupling it to Symfony.

## Deliverables

- Finalize `ProfileProviderInterface`, `ReplacementStrategyInterface`, and
  `PayloadCodecInterface` as the only core extension ports.
- Explicit registration and duplicate-ID validation for profiles, strategies,
  and codecs.
- Built-in JSON embedded-payload support using `embedded: { codec, profile }`.
- Optional `star_mask` replacement strategy; `[REDACTED]` remains the default.

## Acceptance tests

- Contract tests prove custom providers, strategies, and codecs work through
  public interfaces only.
- JSON payloads anonymize through referenced profiles and report codec errors.
- Duplicate and unknown IDs fail before data processing where statically known.
- Star masking preserves the expected string length.

## Handoff

Mark core interfaces as v1. Add an extension-author guide; later packages may
add adapters but must not expand traversal internals.
