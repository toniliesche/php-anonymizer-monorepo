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
