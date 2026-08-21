# Refined Iteration 12 Implementation Plan — Typed Privacy Transformations

## Goal

Add additive, leaf-only value transformations for typed privacy operations while
leaving the existing string `strategy` contract and `AnonymizerInterface`
unchanged. Transformations must work consistently for array APIs, embedded
payloads, reports/audit output, preserve mode, and Symfony Serializer objects.

## Profile and API contract

Use a named map in each leaf rule:

```yaml
field: email
transform:
  type: partial_mask
  options:
    suffix: 4
```

`strategy`, `transform`, and `embedded` are mutually exclusive leaf actions.
Existing traversal, `when`, `children`, and `each` semantics remain unchanged;
transformations are leaf-only.

Add a transformation port and context types in the core package:

- `ValueTransformationInterface`, with supported input types, output type (or
  same-as-input), option validation, and execution.
- `ContextualValueTransformationInterface`, receiving profile, JSON Pointer,
  key ID, and application attributes through a dedicated context object.
- A validated transformation registry with duplicate/unknown-ID diagnostics.

Extend `ArrayAnonymizer` and `AnonymizerFactory` with one final optional
transformations iterable. Existing positional constructor/factory calls remain
valid, and no method is added to `AnonymizerInterface`.

## Core transformations

Register these built-ins by default:

- `nullify`: scalar or temporal input to `null`; no options.
- `partial_mask`: UTF-8 strings; `prefix` defaults to `0`, `suffix` to `4`,
  `character` to `*`; replace all non-visible characters.
- `date_generalize`: native `DateTimeInterface` or strict RFC 3339 strings;
  required `unit` (`year`, `month`, `day`, or `hour`); preserve timezone and the
  input representation category.
- `numeric_bucket`: integers/floats; positive `size`, optional numeric
  `origin` defaulting to `0`; floor to the lower bucket and preserve integer vs
  float output.

Validate options and runtime input/output types before execution. Failures must
identify profile, rule/path, transform, and option/type without exposing source
values.

## Keyed temporal shifting

Implement `date_shift` in the optional `anonymizer-pseudonymization` package,
reusing its `PseudonymKeyProviderInterface`; core must not depend on that
package. Require contextual execution, a non-secret `key_id`, positive
`max_days` (maximum 36,525), and a present `scope_attribute`.

Derive a bounded calendar-day offset from a domain-separated HMAC over key ID,
profile, JSON Pointer, and the selected scope attribute/value. Do not include
the source date in the key material. Equal subjects retain date intervals while
different scopes and paths remain unlinkable; preserve timezone and input
representation.

## Symfony and Bundle integration

Add a serializer-package `ContextualObjectAnonymizerInterface` with contextual
object/report methods. `SymfonyObjectAnonymizer` normalizes objects, delegates
through `ContextualAnonymizerInterface`, and denormalizes while preserving source
object immutability.

Because Symfony normalizers represent dates as strings, accept both native date
objects (array APIs) and strict RFC 3339 strings (normalized objects/payloads).
Keep each representation category on output. Add the
`anonymizer.value_transformation` extension tag, register core transforms by
default, and register `date_shift` with the existing pseudonymization setup.
Publish contextual aliases only when the corresponding capability is enabled.

## Traversal, reporting, and compatibility

Apply transformations in redact and preserve modes after existing condition
snapshots and traversal selection. Retain nested rules, `each`, embedded payload
handling, report paths, and audit aggregation. Report transformed leaves with
action `transform` and the transform ID as handler. Existing strategy rules and
profiles must produce byte-for-byte compatible results.

## Tests and documentation

Cover profile parsing and invalid action combinations; registry validation;
defaults and boundaries for every core transform; Unicode masking; numeric
subtype preservation; timezone/DST and RFC 3339 round-trips; deterministic
shifting, key rotation, scope/path separation, missing context/key failures;
preserve mode, conditions, nested/`each` rules, embedded payloads, reports,
and safe audit output. Add Symfony object tests for normalized dates,
immutability, serializer failures, and conditional Bundle aliases/tags.

Update `docs/profile-schema.md`, `docs/extension-author-guide.md`, package
READMEs/changelogs, metadata and consumer examples. Run `composer test`, the
full `composer qa` gate, and `graphify update .`; do not commit generated graph
caches.

## Acceptance constraints

- No source values, raw dates, secrets, or replacements appear in exceptions,
  reports, audit entries, or logs.
- No container/arbitrary object transformation in this iteration.
- Profiles using `date_shift` require the optional pseudonymization package and
  enabled contextual Bundle integration.
- Existing public APIs and profile behavior remain source-compatible.
