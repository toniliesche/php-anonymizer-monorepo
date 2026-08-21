# Refined Iteration 15 — Profile Governance and Controlled Reuse

This document turns [Iteration 15](iterations/15-profile-governance-and-controlled-reuse.md)
into an implementation-ready specification. The feature is compile-time only:
profile metadata and fragment composition must never change anonymization
traversal, replacement handlers, or runtime expression evaluation.

## Goals and compatibility

- Make profile ownership, lifecycle, purpose, and version information optional,
  validated, immutable, and inspectable.
- Allow explicitly named, profile-local rule fragments with deterministic
  expansion at profile construction/startup.
- Keep existing profile definitions valid and behaviorally byte-for-byte
  compatible when they do not use metadata or fragments.
- Keep reports, audit entries, logs, and exceptions safe: they may identify
  profiles, fragments, selector locations, and fingerprints, but never include
  input values, replacement values, secrets, or raw profile definitions.
- Do not introduce profile inheritance, cross-profile fragment catalogs,
  callbacks, arbitrary expressions, or runtime composition.

## Profile input schema

`ProfileDefinition::fromArray()` accepts two additional optional keys:

```php
[
    'mode' => 'redact',
    'metadata' => [
        'schema_version' => '1',
        'profile_version' => '2026.08',
        'owner' => 'privacy-platform',
        'status' => 'active',
        'purpose' => 'Customer export anonymization',
    ],
    'fragments' => [
        [
            'name' => 'contact-fields',
            'rules' => [
                ['field' => 'email'],
                ['field' => 'phone'],
            ],
        ],
    ],
    'rules' => [
        ['use' => 'contact-fields'],
        ['field' => 'id'],
    ],
]
```

### Metadata

- `metadata` is omitted for legacy profiles or is a non-empty map.
- Only `schema_version`, `profile_version`, `owner`, `status`, and `purpose`
  are allowed. Unknown keys, non-string values, and empty/whitespace-only
  strings are rejected.
- `status` accepts only `active` and `deprecated`.
- Metadata is represented by an immutable `ProfileMetadata` value object and is
  returned only through the explicit inspection API. Documentation must state
  that metadata is governance information and must not contain secrets.

### Fragments and composition entries

- `fragments` is an ordered list of maps, each containing exactly `name` and
  `rules`; this preserves declaration order and lets the framework-independent
  compiler diagnose duplicate names.
- Fragment names use the existing profile-name grammar.
- A composition entry is a map containing exactly `use` and a valid fragment
  name. It may occur in top-level `rules`, nested `children`, or list-form
  `each` rule lists. It is forbidden in `global_rules`.
- Fragments may reference other fragments. References are profile-local and
  cannot cross profile or embedded-profile boundaries.
- Existing rule validation remains authoritative for literal rules inside
  fragments and for the final expanded rule lists.

## Composition compiler

Add a dedicated profile composition component in the domain/profile layer. It
must run from `ProfileDefinition::fromArray()` (and therefore during Bundle
container compilation) and return ordinary immutable `RuleDefinition` lists.

Expansion rules are:

1. Expand references depth-first in declaration order.
2. Preserve the first fragment-sourced rule for a field at a given rule-list
   level; later fragment-sourced rules for that field are ignored consistently.
3. A literal rule declared directly in the containing profile replaces a
   fragment-sourced rule selecting the same field at that level, independent of
   whether the literal rule appears before or after the `use` entry.
4. Two literal/direct rules selecting the same field at one level are rejected
   as an ambiguous override. Existing duplicate-field validation should use a
   contextual composition diagnostic.
5. Preserve the resulting declaration order for all non-conflicting rules;
   replacement must not reorder unrelated rules.

Diagnostics must identify only the profile, fragment, reference path, and
selector location. Required failures include duplicate fragment names, missing
fragment references, cycles, malformed `use` entries, and ambiguous direct
overrides. Cycle diagnostics must include the reference chain without dumping
the source definition.

The compiler should retain ordered fragment provenance internally for inspection
and deterministic diagnostics, but provenance must not be exposed as raw rules.

## Fingerprints and inspection API

Replace the current object-serialization fingerprint with a canonical SHA-256
representation of the composed executable profile: profile name, mode, root,
key-normalization setting, compiled rules, and global rules. Exclude metadata
and fragment provenance so governance-only edits do not change the behavior
identity.

Add these framework-independent public contracts:

- `ProfileInspectorInterface`: inspect one named profile and enumerate safe
  profile summaries.
- `ProfileInspector`: implementation backed by `ProfileProviderInterface`.
- `ProfileInspection`: immutable summary containing profile name, optional
  `ProfileMetadata`, behavior fingerprint, and ordered fragment names.
- `ProfileMetadata`: immutable typed accessors for the five governed fields.

The inspector must never return raw arrays for rules/fragments, selector paths,
handler IDs, key material, source values, or replacement values. A missing
profile returns the established not-found result/exception convention used by
the profile provider layer.

The Symfony Bundle registers the inspector against the composite profile
provider and exposes `ProfileInspectorInterface` as a public alias. Core users
can construct the inspector from any existing profile provider.

## Files and subsystem changes

- Domain profile parsing/value objects: metadata validation, fragment schema,
  composition compiler, safe diagnostics, and canonical fingerprinting.
- Bundle configuration and extension: accept/normalize metadata and ordered
  fragment lists, compile during container setup, and register the inspector.
- Profile documentation and author guide: schema examples, precedence rules,
  limitations, governance guidance, and safety guarantees.
- Changelogs and consumer examples: describe the additive schema and migration
  impact; no migration is required for existing profiles.

## Test plan and acceptance criteria

### Core profile tests

- Metadata omitted, each metadata field individually supplied, all fields
  supplied, invalid status, empty/non-string values, unknown keys, and
  immutable/accessor behavior.
- Existing metadata-free profiles produce identical anonymized output and
  retain all existing validation behavior.
- Ordered fragment parsing, nested references, references in `children` and
  `each`, first-fragment collision behavior, direct-rule replacement, and
  unchanged global-rule restrictions.
- Duplicate fragment names, missing references, malformed entries, cycles, and
  duplicate direct rules produce deterministic safe diagnostics.
- Equivalent composed behavior has the same fingerprint despite metadata or
  fragment provenance changes; different executable composition changes it.

### Inspection and audit tests

- Inspector summaries expose only the approved fields and ordered fragment
  names.
- Audit entries and audit logger context contain profile/fingerprint/path/
  action/handler/count only; no metadata dumps, raw rules, source values, or
  replacements.
- Error messages for every composition failure are safe under adversarial
  values in input data, replacement output, and profile options.

### Symfony integration tests

- Valid configured metadata/fragments compile and register profile services.
- Invalid metadata, duplicate names, missing references, and cycles fail during
  configuration/container loading with contextual paths.
- `ProfileInspectorInterface` resolves from the compiled container and sees
  configured plus tagged profiles through the composite provider.

Run focused PHPUnit suites first, then `composer test`, `composer static`,
`composer lint`, `composer refactor:check`, and the full `composer qa` gate.
Refresh Graphify after implementation; generated graph caches remain
uncommitted.

## Release and rollout assumptions

- This is an additive, opt-in schema extension; profiles without `metadata` or
  `fragments` require no migration.
- No runtime feature flag is required because composition occurs before an
  anonymizer handles data.
- Release notes must call out the new ordered fragment syntax, direct-rule
  precedence, first-fragment collision rule, and the fact that metadata is not
  part of the behavior fingerprint.
