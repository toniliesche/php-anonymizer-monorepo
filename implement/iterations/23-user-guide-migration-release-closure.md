# Iteration 23 — User Guide, Migration, and Release Closure

## Status

Planned.

## Goal

Prepare the clean, synchronized 2.0.0 release without changing runtime
anonymization behavior. Establish task-oriented documentation, preserve a clear
v1-to-v2 migration path, ship focused package-local guides in every archive,
and make release readiness verifiable.

## Deliverables

- Add a canonical task-oriented user guide covering plain PHP, profiles,
  conditions, strategies, transformations, embedded payloads, pseudonyms,
  limits, audits, batches, documents, NDJSON, Symfony objects, and Laravel.
- Add a 2.0 migration guide covering the builder, unified façade, removed and
  internalized v1 entry points, framework rebinding, and legacy-import status.
- Keep the existing v1 migration guide as historical legacy-to-1.x guidance,
  with a clear link to the 2.0 migration guide.
- Reduce root and package READMEs to concise installation, examples, capability
  boundaries, and valid links to focused package-local documentation.
- Ship focused documentation in all seven package archives, with full core
  profile/extension/migration guidance and integration-specific extracts for
  Bundle, Laravel, Serializer, Faker, Personas, and Pseudonymization.
- Execute marked plain-PHP documentation examples in CI and cover framework
  examples through clean Symfony and Laravel consumer fixtures.
- Align published package constraints, branch aliases, changelogs, release
  readiness checks, archive contents, manifests, and 2.0 consumer smoke tests.

## Acceptance

- Documentation examples execute successfully against the documented public
  API.
- Every package archive contains its README, changelog, source, and focused
  documentation while excluding tests and development-only files.
- Release readiness rejects incomplete package sets, stale constraints or
  aliases, missing 2.0 changelogs, and missing package documentation.
- The complete consumer and release-validation suite passes for the
  synchronized 2.0.0 train.
- No runtime anonymization behavior or public API semantics change in this
  iteration.

## Handoff

The iteration is complete when the documentation, migration, package metadata,
consumer, artifact, and release checks pass. Tagging and publishing remain
release-workflow actions and are not performed as part of implementation.
