# Iteration 15 — Profile Governance and Controlled Reuse

## Goal

Improve profile ownership, lifecycle visibility, and safe reuse without
changing anonymization behavior or introducing runtime expression evaluation.

## Deliverables

- Optional profile metadata for schema/profile version, owner, deprecation
  state, and purpose; metadata is validated and inspectable but does not affect
  traversal or replacement behavior.
- Explicitly named reusable rule fragments compiled into profiles at startup.
- Deterministic fragment expansion with diagnostics for duplicate names,
  ambiguous overrides, missing references, and cycles.
- Safe audit/inspection visibility for the composed result. Do not add
  inheritance, arbitrary runtime profile expressions, or callbacks.

## Refined decisions

- Profile metadata is optional. When present it is a non-empty map containing
  any subset of `schema_version`, `profile_version`, `owner`, `status`, and
  `purpose`; unknown keys and invalid values are rejected. `status` is limited
  to `active` and `deprecated`.
- Fragments are profile-local and represented as an ordered list of named
  entries (`name` plus `rules`). The ordered list preserves duplicate-name
  diagnostics and deterministic expansion across PHP and Symfony adapters.
- A composition entry has the form `{'use': 'fragment-name'}` and is allowed
  in top-level `rules`, nested `children`, and list-form `each` rules. Global
  rules remain non-composable.
- Expansion is depth-first and startup-only. Fragment-to-fragment collisions
  keep the first expanded rule; direct profile rules automatically replace
  fragment rules selecting the same field at the same rule-list level. Two
  direct rules selecting the same field are rejected as an ambiguous override.
- Duplicate fragment names, missing references, malformed composition entries,
  and cycles fail with contextual diagnostics that expose only profile,
  fragment, and selector locations.
- Add a dedicated profile inspector API returning only profile name, validated
  metadata, behavior fingerprint, and ordered fragment names. It must never
  expose raw definitions, selector rules, source/replacement values, or
  secrets through inspection, reports, logs, or errors.
- Fingerprints hash the canonical composed executable profile and exclude
  metadata and fragment provenance, so governance-only edits do not alter the
  audit identity of equivalent behavior.

## Acceptance tests

- Cover metadata parsing, visibility, invalid values, and byte-for-byte
  compatibility of profiles without metadata.
- Cover fragment expansion, nested references, duplicate names, ambiguous
  overrides, missing fragments, cycle diagnostics, deterministic compiled
  output, and safe audit fingerprints.
- Verify that metadata and composition cannot expose source values, secrets,
  replacements, or raw profile definitions through reports, logs, or errors.

## Handoff

Update the profile schema, extension-author guide, package documentation,
changelogs, and consumer examples. Release only after compatibility, audit,
quality, and package-install checks pass.
