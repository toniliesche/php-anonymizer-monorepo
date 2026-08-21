# Post-Iteration-9 Roadmap

This roadmap covers the first enhancements after the 1.0.0 release of the
Iteration 9.1 refactor. Iterations 10 onward are additive within the 1.x API
and prioritize production safety before new anonymization capabilities.

## Release Baseline

- Publish the completed Iteration 9.1 refactor as `1.0.0`; update package
  constraints and release documentation accordingly.
- Publish the first Iteration 10 slice as `1.1.0` and the second as `1.2.0`.
- Preserve the existing `AnonymizerInterface`; all new capabilities use
  narrowly typed additive interfaces.

## Iteration 10.0 / 1.1 — Performance and Execution Limits

Performance acceptance is defined primarily around batch exports. Benchmarks
use synthetic, non-sensitive fixtures and measure median runtime, throughput,
and peak memory for 10,000 records across flat redaction, nested/conditional
rules, preserve mode, embedded JSON, and Serializer adaptation.

- Make `anonymize()` use a no-report traversal path; it must not collect
  anonymized or missing paths when callers request only transformed data.
- Preserve condition semantics while replacing full-map condition snapshots
  with memoized snapshots of only the condition paths required at that map
  level. Evaluate each condition once per rule/map pair.
- Eliminate the JSON codec's second full decode while retaining object-root
  rejection and existing error semantics. Detect the JSON root before the
  single associative decode so `{}` and `[]` remain distinguishable.
- Add immutable `AnonymizationLimits` with core defaults of 10 MiB payload input,
  traversal depth 64, 100,000 visited nodes, 50,000 entries per collection,
  and embedded-payload depth 8.
- Apply byte limits to encoded-payload entry points and structural limits to
  arrays. Share one budget across embedded payload traversal and include the
  profile, limit/value, JSON Pointer, and codec where applicable in failures.
- Expose the limits through optional Bundle configuration under
  `anonymizer.limits`; application configuration overrides core defaults.
- Run a dedicated non-blocking benchmark job on a pinned CI runner and compare
  each result with the latest `main` baseline. Flag median-runtime regressions
  above 15% and peak-memory regressions above 10%; keep the normal PR quality
  gate deterministic.

## Iteration 10.1 / 1.2 — Batch Processing and Safe Auditability

- Add additive `BatchAnonymizerInterface::anonymizeRecords(string $profile,
  iterable $records): iterable`. Process map records lazily one at a time,
  reset execution limits per record, retain no prior records or reports, and
  fail when the failing record is pulled. Preserve the underlying exception
  category and include the zero-based record ordinal.
- Add an array-only `AuditAnonymizerInterface` with
  `anonymizeWithAudit()` returning transformed data plus immutable aggregate
  audit data.
- Aggregate audit entries by profile name, deterministic canonical SHA-256
  profile fingerprint, JSON Pointer, action, and nullable strategy/codec ID.
  Order entries deterministically and include counts only; never include source
  values, replacements, raw payloads, or raw profile definitions.
- Keep batch operations output-only in this release. Defer batch audit to the
  document-streaming iteration.
- Add optional Bundle audit wiring, disabled by default. When enabled, publish
  aggregate audit data as one PSR-3 `info` record through the configured logger
  service and expose the audit interface alias only while enabled. Keep PSR
  logging out of the core package.
- Test lazy iteration, map validation, fail-fast behavior, bounded memory,
  deterministic audit ordering, fingerprint stability, strategy/codec
  attribution, and the absence of source or replacement values in serialized
  audit/log contexts.

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

## Iteration 14 — Key Normalization and Global Rules

- Add optional comparison-only ASCII key normalization for equivalent
  camelCase, PascalCase, snake_case, kebab-case, acronym, separator, and digit
  boundary spellings.
- Preserve original dataset keys in output, reports, audit paths, and payloads;
  exact-spelling normalized collisions win, then declaration order.
- Add profile-local global scalar rules, including `each: true` lists, at every
  map depth and inside maps nested in lists.
- Reject structural global rules and keep global rules isolated from embedded
  profiles; retain existing preserve/redact and contextual semantics.
- Release this iteration as `1.5.0`; the former governance/reuse scope moves to
  Iteration 15.

## Iteration 15 — Profile Governance and Controlled Reuse

- Add profile metadata for schema/profile version, owner, deprecation state,
  and purpose; metadata must not affect anonymization behavior.
- Add explicitly named reusable rule fragments compiled into profiles at
  startup.
- Reject duplicate fragment names, ambiguous overrides, and fragment cycles.
- Do not add inheritance or arbitrary runtime profile expressions.
- Make composition deterministic and inspectable through the safe audit API.
- Test fragment expansion, cycle diagnostics, compatibility of existing
  profiles, metadata visibility, and deterministic compiled output.

## Iteration 16 — Portable Profile Providers

- Keep the existing `ProfileProviderInterface::profiles(): iterable` as the
  stable v1 extension port for library-owned rule sets.
- Add `AnonymizerFactory::fromProfileProviders(iterable $providers, ...)` as an
  additive plain-PHP construction helper. Flatten providers in caller order and
  retain duplicate-name and profile-reference validation.
- Document provider-package conventions, explicit opt-in registration, and
  collision-resistant dotted profile names such as `vendor.package.profile`.
- Retain Symfony’s `anonymizer.profile_provider` tag and define Laravel’s
  corresponding service-provider registration in Iteration 17.
- Do not add Composer scanning, provider metadata, inheritance, or profile
  overrides.
- Test deterministic composition, generator-backed providers, duplicate names,
  invalid references, package installation, and unchanged existing factory
  behavior.

## Iteration 17 — Laravel Framework Integration

- Add an auto-discoverable `php-anonymizer/anonymizer-laravel` package for
  Laravel 13 and PHP 8.5, using service providers and dependency injection
  without facades.
- Publish Laravel configuration for local profiles and execution limits, then
  compose configured profiles with explicitly tagged providers, strategies,
  codecs, and transformations during application boot.
- Bind the core and applicable additive anonymizer interfaces, with optional
  contextual capabilities enabled only when their dependencies are present.
- Define the library-author convention for tagging a package-provided
  `ProfileProviderInterface` implementation as
  `anonymizer.profile_provider`.
- Support the existing standalone Symfony Serializer adapter in Laravel when
  explicitly enabled and supplied with an application-owned
  `Symfony\Component\Serializer\SerializerInterface`; the Symfony bundle is
  not required and the Laravel package must not construct the Serializer.
- Keep Eloquent adapters, generic Laravel object normalization, API Resource or
  HTTP response mutation, middleware, and automatic model-profile mapping out
  of scope.
- Test package discovery, boot/configuration validation, tagged composition,
  optional Serializer binding, DTO round-trips, source immutability, failure
  diagnostics, package installation, and unchanged array/document behavior.

## Compatibility and API Policy

- Keep the 1.0 source contract stable: do not add methods to
  `AnonymizerInterface`, change return types, or change existing profile
  output.
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
- Run the established quality gate plus contract suites for limits, batch
  processing, audit redaction, contextual strategies, typed transformations,
  and streaming codecs.
- For iterations 10–12, review error messages, logs, audit artifacts, and
  secret handling as explicit security acceptance criteria.
