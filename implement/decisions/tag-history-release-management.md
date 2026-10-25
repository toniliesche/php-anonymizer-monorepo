# Decision: Tag-History Release Management

## Decision

Use Git tags as the exclusive source of published package versions. Do not keep
`build.properties`, version-bump targets, or Composer `version` fields.

Packages are developed in one monorepo and released in lockstep. Every package
is split/published with the same unprefixed, stable SemVer tag (for example,
`2.0.0`). Releases support stable tags only; do not publish beta,
release-candidate, or hotfix suffixes.

## Repository and manifest contract

- The development monorepo is `toniliesche/php-anonymizer-monorepo`.
- Keep `toniliesche/php-anonymizer` as the core package repository so its
  historical tags and the existing `php-anonymizer/anonymizer` Packagist
  source remain intact.
- Publish each integration package to the matching
  `toniliesche/php-anonymizer-<suffix>` repository. Composer names remain
  `php-anonymizer/<package directory>`.
- Publish each split to the major branch derived from the release version
  (`2.0.0` publishes to `2.x`) and create the same unprefixed version tag in
  every package repository.
- Persist a JSON manifest with `schema_version: 1`, release identity
  (`version`, `branch`, `main_branch`, `source_sha`), and one entry per package
  directory. Each package entry records `composer_name`, `repository`,
  `publication_branch`, `split_sha`, and `archives`; `archives` contains `zip`
  and `tar_gz` entries with a filename and lowercase SHA-256 digest.
- The manifest is the release identity for retries. Resume uses its recorded
  version, source commit, and split commits instead of planning a new version.

## Branch and version rules

- `develop` receives feature work; `main` contains released code.
- A maintainer creates `release/X.Y.x` from `develop` to stabilize a chosen
  major/minor line.
- Only fixes, documentation, dependency updates, and release preparation enter
  a release branch.
- The normal release trigger is a merged pull request from `release/X.Y.x`
  into `main`. The serialized GitHub Actions workflow also supports an explicit
  manual recovery run with a fixed version, source SHA, and release branch.
- The workflow reads `X.Y` from the source branch and finds the highest stable,
  reachable `X.Y.Z` tag on `main`.
  - With no matching tag, the release is `X.Y.0`.
  - Otherwise, the release is `X.Y.(Z + 1)`.
- The first 2.0 release is `2.0.0` from `release/2.0.x` when no matching 2.0
  tag is reachable from `main`.

## Automation and safeguards

- `composer release:preview` reports the calculated version and the tag history
  used to obtain it.
- `composer release:verify` requires full Git history and tags, validates the
  release-branch source, a clean working tree, an unreleased changelog entry for
  the calculated version, quality gates, and tag absence.
- CI alone runs `composer release:publish`; it supports `--dry-run`, validates
  the full package train, publishes all seven package splits and their
  archives, verifies Packagist and consumer installation, and synchronizes
  `main` back to `develop`.
- Gitea Actions runs validation and retains diagnostics; GitHub Actions remains
  the only release publisher. The release workflow passes GitHub, package
  repository, and Packagist credentials only to its publication step.
- Keep the existing core Packagist registration and GitHub repository. Provision
  six integration repositories and Packagist packages once; each release
  publishes all seven packages together.
- Release tooling is implemented in portable PHP and invoked through Composer;
  Make only provides aliases.

## References from the previous library

Retain the SemVer/Keep-a-Changelog convention, `release/*` branch model,
clean-tree/tag checks, and dry-run concept. Avoid tracked version state,
Make-driven arithmetic, commit-subject version detection, and incomplete
release actions.
