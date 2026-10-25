# Refined Iteration 13 Implementation Plan — Document Payloads and NDJSON Exports

## Goal

Additive document and streaming payload APIs for large exports while keeping
the existing map-only payload contract and `AnonymizerInterface` source
compatible. Publish this iteration as the next minor release, `1.4.0`.

## Profile and document contract

Add an optional profile key:

```yaml
root: map # default; list is also supported
```

`root` accepts only `map` or `list` and defaults to `map`, so existing profile
definitions remain valid. A list-root document is a list of associative-map
items; scalar list items and nested list items are rejected with safe,
profile/path-aware errors. Rules continue to describe fields of each map item.

Introduce a separate `DocumentPayloadCodecInterface` for codecs that can
decode and encode either map or list roots. Keep `PayloadCodecInterface`
map-only so existing payload callers and third-party codecs do not change.
The core JSON codec and the Bundle YAML codec implement the document
interface; document calls fail clearly when a selected codec supports only the
legacy map contract.

Add `DocumentAnonymizerInterface` and contextual document methods through a
separate contextual interface. Return document-specific result types capable
of representing either map or list data and their report events. Existing
`anonymizePayload*()` methods continue to reject list roots and retain their
current error messages and result types.

Document traversal must:

- apply one shared execution budget to the complete document;
- preserve conditions, nested rules, `each`, embedded payloads,
  transformations, preserve mode, and contextual transformations;
- compose report pointers with list indexes (`/0/email`) and embedded paths;
- reject a root shape that disagrees with the profile's explicit `root` value.

## NDJSON streaming

Add `NdjsonAnonymizerInterface` with a lazy API accepting and yielding
`iterable<string>` logical lines. Add a separate contextual interface for
context-dependent transformations.

NDJSON profiles must declare `root: list`. Each input element is one JSON
object record; blank lines, malformed JSON, scalar roots, list roots, and
non-string elements fail when the corresponding record is pulled. Errors
include the profile, zero-based record ordinal, and safe decode/anonymize/encode
stage without exposing source data. Output elements are compact JSON objects
without trailing line terminators and preserve input order.

Execution limits reset for each record. Processing remains lazy and retains no
previous records. A stream result exposes lazy output lines and a final audit
view; audit is available only after successful exhaustion. Partial consumption
or a failed stream does not produce an audit result.

Aggregate stream audit entries by profile, compiled-profile fingerprint,
wildcard record path (for example `/*/email`), action, and handler. Counts
must remain bounded by profile complexity rather than record count and must not
contain source values, replacements, raw payloads, or profile definitions.

## Bundle integration and boundaries

Register document and NDJSON interfaces in the Bundle and expose their aliases
alongside the existing array and batch services. Extend the existing optional
audit logger to publish the aggregate NDJSON audit only after successful stream
completion. Keep Symfony object adaptation, CSV, and XML outside this
iteration.

## Tests and documentation

Add tests for:

- profile `root` parsing, defaults, invalid values, and root-shape failures;
- JSON/YAML map and list documents, including nested and embedded paths;
- document reports, transformations, contextual execution, and execution
  limits;
- NDJSON laziness, ordering, bounded memory, record-level limit reset, and
  malformed/non-object record failures;
- wildcard audit aggregation, successful finalization, incomplete streams,
  and secret/source-value exclusion;
- Bundle codec registration, aliases, and conditional audit logging.

Update `docs/profile-schema.md`, `docs/extension-author-guide.md`, package
READMEs and changelogs, consumer examples, and the Iteration 13 roadmap entry.
Run `PHP_VERSION=8.5 composer test`, the full QA gate, and `graphify update .`.
Create annotated `iteration-13` and `1.4.0` tags only after all checks pass.

## Compatibility and acceptance constraints

- Do not add methods to or alter return types of `AnonymizerInterface`.
- Existing map payload behavior remains byte-for-byte compatible.
- Document and NDJSON APIs are additive and use narrowly typed interfaces.
- No raw values, secrets, or replacements appear in errors, reports, audits, or
  logs.
- No arbitrary object transformation or implicit list inference is introduced.
