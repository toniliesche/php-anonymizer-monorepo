# Refined Iteration 9 Implementation Plan — Release Hardening

## Summary

Iteration 9 prepares the rewrite for a controlled `1.0.0` release. The
iteration delivers self-contained package documentation, deterministic release
artifacts, and a tag-history-driven GitHub release workflow.

This iteration publishes GitHub release assets only. Packagist registration,
package-repository splitting, and Composer distribution from separate package
repositories are explicitly deferred.

The development milestone remains `iteration-9`; the stable `1.0.0` tag is
created only by the merged release workflow.

## Documentation and package contents

- Consolidate the Iterations 1–9 material into a root `1.0.0` changelog entry,
  while retaining an empty `Unreleased` section for future work.
- Add a package-local `CHANGELOG.md` to every published package and add the
  corresponding `1.0.0` entry to each package changelog.
- Make every package README self-contained. Remove links that point outside a
  package archive and document installation, public entry points, supported
  configuration, and the relevant examples.
- Add package-local documentation/examples for the core profile schema and
  extension contracts, Bundle/Flex setup, Serializer object behavior, Faker,
  Personas, and legacy-rule migration as applicable.
- Add an explicit v1 migration guide covering legacy-rule import, unsupported
  legacy features, Serializer-based object handling, optional integrations, and
  breaking API/configuration changes.
- Add package-level `.gitattributes` rules so release archives include source,
  README, changelog, documentation, and examples while excluding tests and
  repository-only development configuration.

## Release calculation and verification

- Replace placeholder release scripts with small, testable PHP release
  services and CLI adapters under `tools/`.
- Calculate versions only from a `release/X.Y.x` branch and stable tags
  reachable from `main`: use `X.Y.0` when no matching tag exists, otherwise
  increment the highest matching patch number.
- Reject malformed release branches, missing `main`, shallow repositories,
  dirty worktrees, missing full tag history, and a target tag that already
  exists anywhere in Git.
- Require the release source to be a merged `release/X.Y.x` change into
  `main`, and verify that the root and every package changelog contain the
  calculated release entry.
- Verify that the complete expected package set is present and that all
  release artifacts use the one calculated version. Do not add Composer
  `version` fields or tracked version properties.
- Keep `release:preview` read-only. Make `release:verify` execute all release
  preconditions and make `release:publish --dry-run` print the exact tag,
  release, archive, checksum, and synchronization actions without changing
  Git or GitHub.

## Archives and GitHub workflow

- Build deterministic ZIP and TAR.GZ archives from the verified release commit
  for each of the five packages, with package-specific archive prefixes.
- Generate SHA-256 checksums and a release manifest mapping every package to
  the shared version, archive names, and hashes.
- Validate each extracted archive with Composer metadata validation and inspect
  its documentation and excluded-file set before publication.
- Keep the release workflow on full Git history. For a merged
  `release/X.Y.x` pull request into `main`, run `composer qa`, run release
  verification, create the annotated stable tag, and publish a GitHub release
  containing the archives, checksums, manifest, and generated release notes.
- Grant only the required GitHub Actions permissions: `contents: write` for
  the tag/release and `pull-requests: write` for synchronization.
- After publication, idempotently open a `main`-to-`develop` synchronization
  pull request. Do not push directly to `develop`.
- Treat GitHub release assets as inspection/offline artifacts; existing clean
  consumer tests remain the source-installability check for the package set.

## Tests and acceptance criteria

- Add unit and process-fixture tests for first and patch releases, unrelated
  and malformed tags, pre-existing target tags, invalid source branches,
  missing refs, dirty trees, and shallow checkouts.
- Test changelog/package-set validation and rejection of incomplete or
  inconsistent release inputs.
- Test deterministic archive names, prefixes, checksums, extracted Composer
  manifests, expected documentation, and exclusion of tests/development files.
- Add executable documentation examples covering arrays, JSON, YAML, Faker,
  preserve mode, embedded payloads, tagged extensions, and immutable object
  anonymization.
- Run Composer normalization/validation, PHP-CS-Fixer, Rector checks, PHPStan,
  dependency analysis, Deptrac, PHPUnit with coverage, Infection at the 90%
  threshold, all clean-consumer checks, and release-preview/artifact checks.
- Do not tag `1.0.0` until the complete quality and release verification suite
  passes on the release commit.

## Assumptions and boundaries

- The eventual GitHub repository and credentials are supplied by the hosting
  environment; the current local checkout has no configured remote and cannot
  publish by itself.
- All five packages participate in one shared stable release tag and release
  train.
- Packagist and split-repository publication are a later iteration and are not
  silently implied by the GitHub release assets in this iteration.
