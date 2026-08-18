# Refined Iteration 4 — Extension Ports and JSON

This document refines `iterations/04-core-extensions-and-json.md` into an
implementation-ready increment. It preserves the array-only, framework-free
core boundary while making strategies and payload codecs explicit extension
ports.

## Goal

Finalize the core extension contracts, add named string replacement strategies,
and support JSON both as a top-level payload and as an embedded string. All
configuration is validated before input data is processed.

## Public API and configuration

- Replace lookup-only `ProfileProviderInterface` with
  `profiles(): iterable`, yielding `ProfileDefinition` instances. The core
  eagerly builds a profile registry and rejects duplicate names, missing
  embedded-profile references, and direct or indirect embedded-reference
  cycles during anonymizer construction.
- Add exactly two further core extension ports:
  - `ReplacementStrategyInterface`: `id(): string` and
    `replace(string $value): string`.
  - `PayloadCodecInterface`: `id(): string`, `decode(string $payload): array`,
    and `encode(array $data): string`.
- `ArrayAnonymizer` accepts one profile provider plus iterable custom
  strategies and codecs. It validates duplicate, case-sensitive IDs using
  `^[A-Za-z][A-Za-z0-9_.-]*$`.
- Register built-ins for `redact`, `star_mask`, and `json`.
- Extend `AnonymizerInterface` with `anonymizePayload()` and
  `anonymizePayloadWithReport()`. Add immutable
  `PayloadAnonymizationResult` exposing the encoded payload and nested
  `AnonymizationResult`.

## Rule and runtime semantics

- Extend rule vocabulary to `field`, `children`, `each`, `strategy`, `when`,
  `embedded`, and `on_scalar`.
- `strategy` is leaf-only and cannot be combined with traversal or embedded
  payload handling. Redact mode defaults to `redact`; preserve mode leaves a
  selected leaf unchanged unless an explicit strategy is configured.
- `embedded` is leaf-only and contains exactly `{codec, profile}`. The selected
  value must be a string. Decode it, anonymize the resulting map with the
  referenced profile, and encode it again. Inner report paths are prefixed by
  the containing field path.
- Conditions run before embedded processing and retain Iteration 3 snapshot
  semantics. Preserve input immutability and deterministic RFC 6901 paths.
- Top-level payload methods use the selected codec and profile. Codecs must
  produce a map; JSON list and scalar roots are rejected. JSON is compact and
  uses `JSON_THROW_ON_ERROR`; original whitespace is not preserved.
- `star_mask` emits one `*` per UTF-8 character, including the empty-string
  case.

## Validation, errors, and architecture

- Validate unknown strategy, codec, and profile references, duplicate IDs,
  malformed `strategy`/`embedded` definitions, and embedded-profile cycles
  before processing data.
- Add path-aware codec failures for embedded payloads and contextual
  top-level codec failures. Built-in JSON errors are translated to this
  exception type; arbitrary extension exceptions are not swallowed.
- Keep registries and orchestration in Application/Infrastructure layers.
  Domain remains immutable metadata and has no codec or strategy dependency.
  No Symfony, YAML, Faker, serializer, object, or remote/lazy-provider support
  is introduced.

## Tests, documentation, and handoff

- Add contract tests for enumerable providers, strategies, and codecs, plus
  duplicate and eager reference/cycle validation.
- Cover default redaction, UTF-8 star masking, preserve-mode strategies,
  top-level JSON, embedded JSON, prefixed reports, codec failures, malformed
  roots, and input immutability.
- Update the README, profile-schema reference, extension-author guide, and
  `CHANGELOG.md` under `Iteration 4 — Core Extensions and JSON (Unreleased)`.
- Run `PHP_VERSION=8.5 composer qa`. After the implementation commit is
  created manually, create annotated tag `iteration-4`.

