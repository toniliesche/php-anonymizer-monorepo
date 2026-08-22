# Refined Iteration 23 — User Guide, Migration, and Release Closure

## Summary

Prepare the clean, synchronized 2.0.0 release without changing runtime
anonymization behavior. Establish task-oriented documentation, preserve a clear
v1-to-v2 migration path, ship focused package-local guides in every archive,
and make release readiness verifiable.

## Documentation and public guidance

- Add `docs/user-guide.md` as the canonical application guide. Cover builder
  setup; redact and preserve profiles; nesting, lists, and conditions;
  strategies and transformations; JSON/YAML embedded payloads; keyed
  pseudonyms and contexts; limits, reports, audits, batches, document roots,
  and NDJSON; Symfony object anonymization; and Laravel configuration.
- Add `docs/migration-v2.md` mapping v1.7 usage to 2.0: builder construction,
  unified façade operations, removed factory/concrete-engine/serializer
  dispatchers, internalized collaborators, framework rebinding, and the
  deprecated legacy-import command.
- Keep `docs/migration-v1.md` as historical legacy-to-1.x guidance and add an
  explicit link to the 2.0 migration guide.
- Reduce root and package READMEs to installation, a minimal correct example,
  capability boundaries, and links to local focused guides. Keep
  `docs/profile-schema.md` and `docs/extension-author-guide.md` authoritative
  for schema and extension contracts.
- Ship focused package-local documentation under `packages/*/docs/`: full core
  user/profile/extension/migration guidance in the core archive, and focused
  configuration or usage extracts for Bundle, Laravel, Serializer, Faker,
  Personas, and Pseudonymization. README links must remain valid after archive
  extraction.

## Documentation verification and consumers

- Mark self-contained, plain-PHP examples with a dedicated executable-fence
  marker. Add a Composer documentation check that extracts each marked example
  into an isolated temporary application with its own vendor link, executes it,
  and reports the source document and fence on failure.
- Keep Symfony/Laravel YAML and container examples non-executable at fence
  level; verify their documented behavior through consumer fixtures.
- Extend the public-example boundary checker to include package-local docs.
- Align core, Bundle, Faker, Personas, profile-provider, and Laravel consumer
  fixtures with the documented 2.0 entry points.
- Add standalone Serializer and Pseudonymization consumer smoke tests and
  register both in `composer qa`.

## 2.0 release-train closure

- Update all published package dependencies on the core—and the Bundle’s
  Serializer dependency—to `^2.0`. Set every package branch alias to
  `2.0.x-dev`. Keep workspace path-package dependencies at `@dev` and do not
  add Composer `version` fields.
- Give the root and every affected package changelog an empty `Unreleased`
  section above a `2.0.0` entry describing its actual public, documentation,
  or packaging change.
- Strengthen release readiness for the version calculated from
  `release/2.0.x`: validate the complete seven-package set, package constraints
  and aliases, required 2.0 changelog entries, and each package’s declared
  local documentation.
- Make artifact generation inspect ZIP and TAR.GZ contents for the required
  README, changelog, source, and focused documentation while rejecting tests
  and development-only files. Include the verified documentation files in the
  release manifest.
- Extend release-preview and release-readiness fixtures for the first 2.0.0
  release and a subsequent patch release. Retain the existing tag-history
  workflow and do not tag or publish during this iteration.

## Test plan

- Execute every marked plain-PHP example and assert its documented result.
- Run all consumer fixtures, including object anonymization and keyed
  pseudonym usage from clean temporary installs.
- Test release-readiness failures for missing package docs, missing or
  mismatched changelogs, stale `^1.x` internal constraints, stale branch
  aliases, incomplete package sets, and archive omissions.
- Run focused documentation, consumer, artifact, and release-preview checks,
  then:

  ```bash
  PHP_VERSION=8.5 composer check
  PHP_VERSION=8.5 composer test:coverage
  PHP_VERSION=8.5 composer mutation
  PHP_VERSION=8.5 composer consumer
  PHP_VERSION=8.5 composer consumer:bundle
  PHP_VERSION=8.5 composer consumer:faker
  PHP_VERSION=8.5 composer consumer:personas
  PHP_VERSION=8.5 composer consumer:profile-provider
  PHP_VERSION=8.5 composer consumer:laravel
  PHP_VERSION=8.5 composer release:preview:test
  PHP_VERSION=8.5 composer artifacts
  PHP_VERSION=8.5 composer qa
  ```

- Refresh Graphify after implementation; generated Graphify output remains
  uncommitted.

## Fixed decisions and assumptions

- The 2.0.0 train releases all seven packages in lockstep.
- Package archives contain focused, maintained local documentation rather than
  links to an external repository or documentation site.
- Plain-PHP documentation examples are executable; framework configuration
  examples are verified by Symfony and Laravel consumers.
- Runtime APIs and semantics are frozen. This iteration changes only docs,
  Composer/release metadata, verification, artifacts, and consumer coverage.
