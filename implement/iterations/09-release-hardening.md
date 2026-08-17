# Iteration 09 — Release Hardening

## Goal

Prepare the modular rewrite for a reliable `1.0.0` release.

## Deliverables

- End-to-end examples for arrays, JSON, YAML, Faker, preserve mode, embedded
  payloads, tagged extensions, and object anonymization.
- Package README files, changelogs, API references, Flex documentation, and an
  explicit breaking-change migration guide.
- Release automation that publishes all independently installable packages from
  the monorepo in one shared-version release train, plus package-install smoke
  tests.
- A tag-history release workflow for merged `release/X.Y.x` pull requests,
  using stable tags only and no persisted version properties.

## Acceptance tests

- Core reaches 100% line and branch coverage; the project reaches 95% line
  coverage and a 90% Infection mutation score.
- All examples run in CI and every package can be installed by a clean consumer.
- Every pull request has already passed the full quality suite, including
  Infection and clean-consumer end-to-end tests; release verification reruns it
  from the release commit.
- Release verification rejects a package set whose versions do not match the
  lockstep release version.
- Release tests cover first and patch releases, unrelated/malformed tags,
  duplicate tags, invalid source branches, and a shallow checkout.
- Final API, extension contracts, configuration, and migration documentation are
  reviewed together before tagging `1.0.0`.

## Handoff

Tag and publish `1.0.0` only after all prior iteration acceptance criteria and
release checks pass.
