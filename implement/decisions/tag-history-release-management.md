# Decision: Tag-History Release Management

## Decision

Use Git tags as the exclusive source of published package versions. Do not keep
`build.properties`, version-bump targets, or Composer `version` fields.

Packages are developed in one monorepo and released in lockstep. Every package
is split/published with the same unprefixed, stable SemVer tag (for example,
`1.0.0`). v1 supports stable tags only—no beta, release-candidate, or hotfix
suffixes.

## Branch and version rules

- `develop` receives feature work; `main` contains released code.
- A maintainer creates `release/X.Y.x` from `develop` to stabilize a chosen
  major/minor line.
- Only fixes, documentation, dependency updates, and release preparation enter
  a release branch.
- A merged pull request from `release/X.Y.x` into `main` is the only release
  trigger.
- The workflow reads `X.Y` from the source branch and finds the highest stable,
  reachable `X.Y.Z` tag on `main`.
  - With no matching tag, the release is `X.Y.0`.
  - Otherwise, the release is `X.Y.(Z + 1)`.
- The first v1 release is therefore `1.0.0` from `release/1.0.x`.

## Automation and safeguards

- `composer release:preview` reports the calculated version and the tag history
  used to obtain it.
- `composer release:verify` requires full Git history and tags, validates the
  release-branch source, a clean working tree, an unreleased changelog entry for
  the calculated version, quality gates, and tag absence.
- CI alone runs `composer release:publish`; it supports `--dry-run`, creates the
  tag and GitHub release, publishes/splits all packages, and synchronizes `main`
  back to `develop`.
- Release tooling is implemented in portable PHP and invoked through Composer;
  Make only provides aliases.

## References from the previous library

Retain its SemVer/Keep-a-Changelog convention, `release/*` branch model,
clean-tree/tag checks, and dry-run concept. Replace its tracked version state,
Make-driven arithmetic, commit-subject version detection, and incomplete
release action. The old workflow also targets PHP 8.2 and requires `ext-yaml`,
both incompatible with this v1 plan.
