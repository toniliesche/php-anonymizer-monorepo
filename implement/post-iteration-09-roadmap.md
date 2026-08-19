# Post-Iteration-9 Roadmap

This roadmap covers the first post-1.0 enhancements. It preserves v1 source
compatibility and prioritizes production safety before new anonymization
capabilities.

## Iteration 10 — Performance, Execution Safety, and Safe Auditability

Performance acceptance is defined primarily around batch exports. Benchmarks
use synthetic, non-sensitive fixtures and measure median runtime, throughput,
and peak memory for 10,000 records across flat redaction, nested/conditional
rules, preserve mode, embedded JSON, and Serializer adaptation.

- Run a dedicated non-blocking benchmark job on a pinned CI runner and compare
  each result with the latest `main` baseline. Flag median-runtime regressions
  above 15% and peak-memory regressions above 10%; keep the normal PR quality
  gate deterministic.
- Make `anonymize()` use a no-report traversal path; it must not collect
  anonymized or missing paths when callers request only transformed data.
- Preserve condition semantics while replacing full-map condition snapshots
  with memoized snapshots of only the condition paths required at that map
  level. Evaluate each condition once per rule/map pair.
- Eliminate the JSON codec's second full decode while retaining object-root
  rejection and existing error semantics.
- Add additive `BatchAnonymizerInterface::anonymizeRecords(string $profile,
  iterable $records): iterable`. Process map records one at a time, validate
  before each yielded result, retain no prior records, and intentionally omit
  per-record reports to guarantee bounded memory.

- Add immutable `AnonymizationLimits` for input bytes, traversal depth, visited
  nodes, collection entries, and embedded-payload depth.
- Enforce limits for arrays and payload codecs with path-aware failures.
- Expose limits through optional Bundle configuration with conservative defaults
  and application overrides.
- Add a separate array-focused audit interface/result. Audit entries contain
  profile identity/version, path, action, strategy/codec ID, and aggregate
  counts, never source or replacement values.
- Add an optional PSR-3-compatible audit/metrics adapter in the Bundle; keep the
  core free of logging dependencies.
- Test boundaries, malformed/untrusted input, embedded payloads, deterministic
  audit ordering, and the absence of payload values in audit output.

## Iteration 11 — Keyed Deterministic Pseudonymisation

- Publish an optional `anonymizer-pseudonymization` package with irreversible
  HMAC-based string pseudonyms and stable output encoding.
- Define `PseudonymKeyProviderInterface`; keys come from application code or
  secret-management integrations, never from profile YAML or logs.
- Add an additive contextual-strategy interface carrying profile, JSON Pointer
  path, configured key ID, and application-supplied tenant/scope attributes.
- Keep the existing `ReplacementStrategyInterface` unchanged.
- Support explicit key IDs and domain separation so values can be stable in an
  intended scope but unlinkable across tenants, profiles, or fields.
- Keep reversible token vaults and encryption out of this package; provide them
  only as separate external integrations if required.
- Test determinism, key rotation, tenant/profile/path scoping, Unicode input,
  key-provider failures, and secret non-disclosure in reports and exceptions.

## Iteration 12 — Typed Privacy Transformations

- Preserve existing string `strategy` behavior.
- Introduce a separate leaf-only transformation mechanism for typed values.
- Provide initial built-ins for nullification, partial masking of common
  identifiers, date/time generalisation or deterministic shifting, and numeric
  bucketing.
- Require each transformation to declare supported input types, validated
  options, and output type.
- Reuse contextual execution for transformations requiring tenant scope or a
  keyed offset.
- Test type preservation, timezone/date boundaries, invalid options,
  deterministic results, preserve mode, conditions, nested rules, and embedded
  payloads.

## Iteration 13 — Payload Documents and Large Exports

- Retain the current map-only `anonymizePayload()` contract.
- Add a new document-payload interface for map or list roots.
- Add explicit root-list profile support; do not infer list semantics from
  arbitrary arrays.
- Deliver NDJSON record-by-record processing as the first streaming format,
  with bounded memory and aggregated safe audit output.
- Keep CSV/XML as later codec opportunities until concrete mapping and error
  semantics are defined.
- Test map/list compatibility, malformed documents, record-level failures,
  memory bounds, ordering, and report path composition.

## Iteration 14 — Profile Governance and Controlled Reuse

- Add profile metadata for schema/profile version, owner, deprecation state,
  and purpose; metadata must not affect anonymization behavior.
- Add explicitly named reusable rule fragments compiled into profiles at
  startup.
- Reject duplicate fragment names, ambiguous overrides, and fragment cycles.
- Do not add inheritance or arbitrary runtime profile expressions.
- Make composition deterministic and inspectable through the safe audit API.
- Test fragment expansion, cycle diagnostics, compatibility of existing
  profiles, metadata visibility, and deterministic compiled output.

## Compatibility and API Policy

- Keep v1 source-compatible: do not add methods to `AnonymizerInterface`,
  change return types, or change existing profile output.
- Expose new behavior through narrowly typed interfaces and optional packages.
- Bundle aliases should expose those interfaces only when the capability is
  enabled.
- Keep the condition DSL declarative and safe. Future predicates must be
  validated built-in operators or a separately versioned operator registry;
  arbitrary expressions and callbacks remain unsupported.
- Keep the Serializer bridge optional at the capability level so array-only
  consumers are not forced to adopt object-specific audit or transformation
  APIs.

## Validation

- Every iteration updates the profile schema, extension-author guide,
  changelog, package-install smoke tests, and consumer examples.
- Run the established quality gate plus contract suites for limits, audit
  redaction, contextual strategies, typed transformations, and streaming
  codecs.
- For iterations 10–12, review error messages, logs, audit artifacts, and
  secret handling as explicit security acceptance criteria.
