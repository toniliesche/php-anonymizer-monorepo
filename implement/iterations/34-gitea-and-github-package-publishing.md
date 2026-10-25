# Iteration 34 — Gitea CI and GitHub Package Publishing

## Status

In progress. Tasks 1–9 are implemented. Tasks 10–13 remain.

## Goal

Add Gitea validation while keeping GitHub as the sole release publisher. Publish
all seven Composer packages from the monorepo in lockstep without changing the
existing core Composer package identity or losing its published history.

## Repository and package identities

- Development monorepo: `toniliesche/php-anonymizer-monorepo`.
- Core package repository: existing `toniliesche/php-anonymizer`.
- Integration repositories: `toniliesche/php-anonymizer-<suffix>`, where the
  suffix matches the package directory after `anonymizer-`.
- Composer names stay `php-anonymizer/<package directory>`.
- Release `X.Y.Z` publishes every package to branch `X.x`, with the same
  unprefixed stable version tag. The first planned train is `2.0.0` on `2.x`.
- Preserve existing core branches, tags, Packagist registration, and installable
  versions.

## Shared release contract

`ReleaseTrain` owns the seven-package inventory and maps a package directory to
its Composer name, GitHub repository, and major publication branch. Version
mapping accepts stable `X.Y.Z` versions only.

The version 1 JSON manifest has this shape:

```json
{
  "schema_version": 1,
  "release": {
    "version": "2.0.0",
    "branch": "release/2.0.x",
    "main_branch": "main",
    "source_sha": "<40 or 64 lowercase hexadecimal characters>"
  },
  "packages": {
    "anonymizer": {
      "composer_name": "php-anonymizer/anonymizer",
      "repository": "https://github.com/toniliesche/php-anonymizer.git",
      "publication_branch": "2.x",
      "split_sha": "<40 or 64 lowercase hexadecimal characters>",
      "archives": {
        "zip": {"file": "anonymizer-2.0.0.zip", "sha256": "<64 lowercase hexadecimal characters>"},
        "tar_gz": {"file": "anonymizer-2.0.0.tar.gz", "sha256": "<64 lowercase hexadecimal characters>"}
      }
    }
  }
}
```

`packages` contains every train package exactly once; writers emit entries in
`ReleaseTrain` order. Each entry has the same fields and package-specific
filenames. JSON object member order is not significant. The manifest is immutable
release identity for retry: retries use its version, source SHA, and split
SHAs rather than planning a new version.

## Agent implementation tasks

### Task 1 — Establish shared release contracts — Implemented

- Add Composer name, destination repository, and major publication branch
  mapping to `ReleaseTrain`.
- Define and validate manifest schema version 1, including stable version and
  branch correspondence, complete package inventory, source and split SHAs,
  archive names, and SHA-256 digests.
- Add fixture coverage for all destinations, version mapping, invalid manifests,
  and JSON round trips.
- Update the tag-history release decision with the repository layout and
  manifest contract.

### Task 2 — Create isolated release fixtures

Implemented with reusable temporary Git and loopback HTTP fixture helpers and
registered as `composer release:fixture:test` in QA.

- Build temporary monorepo and bare Git repository fixtures, including legacy
  core history and tags.
- Provide an HTTP fixture for GitHub and Packagist responses.
- Ensure fixtures clean up temporary files and never require live credentials.

### Task 3 — Implement package split planning

Implemented as `ReleaseSplitPlanner` and `ReleaseSplitPlan`, with the
`composer release:split:test` fixture command. The split plan carries package
identity and split SHAs; Task 6 combines it with generated archive checksums to
write the complete manifest schema above.

- Generate all seven splits from an explicit monorepo commit using
  `git subtree split`.
- Validate package roots, Composer names, source, documentation, and internal
  dependency constraints.
- Produce a validated split plan with all package destinations and split SHAs;
  Task 6 combines it with archive checksums for the persisted release manifest.
- Verify deterministic output and that planning does not write to remotes.

The workflows use `tools/install-git-subtree.sh` to install `git-subtree` with
the runtime's package manager and confirm it is available through Git's exec
path before running the split fixture.

### Task 4 — Implement destination preflight and Git publication

Implemented as `GitPackagePublisher` with the
`composer release:publish:test` temporary remote fixture.

- Check all destinations, branches, and tags before the first push.
- Publish each branch and annotated version tag atomically with
  `PACKAGE_PUBLISH_TOKEN`; never force push.
- Reuse identical existing tags on retry and reject conflicting refs or
  branch regressions.
- Preserve legacy core refs.

### Task 5 — Implement Packagist update and verification

Implemented through `PackagistReleaseVerifier`, the
`tools/release-packagist.php` release command, and the
`composer release:packagist:test` HTTP fixture. The release command verifies
Packagist metadata and installs all seven exact versions in a clean consumer.

- Trigger updates with `PACKAGIST_USERNAME` and `PACKAGIST_API_TOKEN` using
  bearer authentication.
- Verify all seven package versions and source references, polling every
  30 seconds for up to ten minutes.
- Test delays, failures, source mismatch, and timeout with HTTP fixtures.
- Install all seven exact versions in a clean consumer without path repositories.

### Task 6 — Implement resumable release orchestration

Implemented by `ReleaseOrchestrator` and `tools/release-publish.php`. The
default Composer command prints the complete dry-run; `--execute` performs the
release and resumes draft releases from their persisted manifest and assets.
GitHub release API behavior has a local HTTP fixture.

- Run readiness checks and full destination preflight before publishing.
- Build all package archives, checksums, and the manifest for a fixed version
  and source SHA.
- Create the monorepo tag and a draft GitHub release; persist release identity
  and artifacts there before split publication.
- Resume from the saved manifest without recalculating a version or replacing
  released content.
- Publish package splits, verify Packagist and clean consumer installation,
  then publish the GitHub release and open or reuse the main-to-develop PR.
- Make the Composer dry run describe the full operation.

### Task 7 — Add checkout, gate logging, and diagnostics helpers

Implemented by `CheckoutVerifier`, `GateRunner`, and their CLI entry points.
`composer ci:verify-checkout` validates revision, history, tags, and the main
baseline; `composer ci:run-gate <name> composer qa` preserves the exit code and
writes logs, fresh coverage, mutation summaries, and run metadata under
`var/ci-diagnostics/`. CI workflows select their own artifact upload action.

- Verify event revision, complete history, tags, and the main baseline.
- Run canonical Composer gates while preserving exit status and collecting logs,
  coverage, mutation summaries, event, revision, run identity, and outcome.
- Keep artifact transport specific to each CI platform.

### Task 8 — Prepare the Gitea PHP runtime

Implemented in the manual `.gitea/workflows/runtime-check.yaml` workflow using
the wedding PHP builder and `linux-amd64-docker` runner. The workflow installs
`git-subtree` through the shared Alpine/Debian installer, checks locked
extensions before Composer install, and confirms the Xdebug coverage report
after a real `composer test:coverage` run. The coverage runner explicitly
disables PCOV when it is loaded.

- Use the wedding PHP builder and `linux-amd64-docker` runner.
- Enable PHP 8.5-compatible Xdebug, disable PCOV for coverage, and check required
  extensions before installing locked dependencies.
- Install `git-subtree` and verify it is available through Git's exec path for
  package split planning and release publication.
- Verify the runtime with an actual coverage invocation.

### Task 9 — Add Gitea validation workflows

Implemented as `.gitea/workflows/quality.yaml`, `release-preview.yaml`, and
`benchmark.yaml`. They use the PHP builder, restrict private image access on
pull requests to same-repository events, retain run diagnostics with Gitea
artifact actions, and smoke-test artifact downloads. The benchmark keeps its
existing path filters and Monday schedule. Workflow YAML and policy checks run
as `composer gitea:workflows:test` in QA.

- Add quality, release-preview, and benchmark workflows without publishing
  credentials.
- Run full QA for internal pull requests and relevant branch pushes; retain
  diagnostics on failure and verify Gitea artifact round trips.
- Support release preview for release-to-main pull requests and explicit manual
  release branch inputs. Preserve benchmark path filters and weekly schedule.
- Restrict jobs needing private registry access to trusted repository events.

### Task 10 — Update GitHub workflows

Implemented in the four `.github/workflows/*.yml` workflows. GitHub Actions are
pinned to full commit SHAs, validation jobs have read-only permissions, and
quality, preview, benchmark, and release jobs upload the run diagnostics. The
release workflow checks out the merged commit SHA (or the explicit manual
recovery SHA), fetches history and tags, checks ancestry against `origin/main`,
serializes publishing, and runs full QA plus the Task 6 dry-run before the
publication step. Manual recovery requires a version, source SHA, and release
branch; package and Packagist credentials are scoped to the publication step.
`composer github:workflows:test` parses the workflows and checks merge gating,
manual preview/recovery inputs, action pins, permissions, diagnostics paths,
benchmark artifacts, and credential scope. The fixture also runs in `composer
qa`.

- Retain quality, release preview, benchmark, and release workflows.
- Pin actions and narrow permissions for validation jobs.
- Check out the exact merged release revision, fetch full history and tags,
  verify release ancestry, serialize publishing, and run QA before release.
- Add manual recovery inputs for explicit version, source SHA, and release
  branch. Connect the workflow to Task 6's release command and pass publishing
  credentials only to the publication job.
- Validate GitHub workflow syntax and test merge eligibility, manual previews,
  recovery inputs, and artifact paths.

### Task 11 — Integrate commands and documentation — Implemented

Implemented the `release:packagist` Composer entry point and documented the
repository layout, one-time repository and Packagist provisioning, GitHub and
Gitea secrets and permissions, first release setup, normal publishing, and
manual recovery in `docs/release-and-publishing.md`. The README now identifies
the 2.x monorepo and links to the guide. Updated developer tooling and release
decisions to describe Gitea validation with GitHub-only publishing, and added a
root changelog entry; package changelogs are unchanged because no package API
or runtime behavior changed.

- Register fixture commands in Composer and the QA gate.
- Document repository provisioning, secrets and permissions, initial setup,
  release, recovery, and six new Packagist registrations.
- Correct stale v1 and GitHub-only publishing descriptions; update applicable
  changelogs.

### Task 12 — Run final local verification — Implemented

Ran the focused release, CI, and workflow fixtures, PHP 8.5 metadata
validation, lint, and full `composer qa`. Applied the Rector and formatter
changes identified during QA. The final QA gate passed, including all 415
PHPUnit tests, 100% line coverage across 1,784 published-package lines,
Infection, consumer installation checks, and release fixtures. Refreshed
Graphify after the tooling changes and confirmed generated analysis output did
not add tracked worktree changes.

- Run focused release and CI fixtures, workflow validation, PHP 8.5 metadata
  validation, lint, and full `composer qa`.
- Refresh Graphify after tooling changes and review the diff for generated files
  and unintended changes.

### Task 13 — Activate and verify publication — Pending external setup

The local release contract, publication fixtures, consumer checks, artifacts,
and workflow diagnostics are implemented and pass `composer qa`. External
activation has not been run: this checkout has no Git remote or local `main`
branch, no `release/2.0.x` branch or 2.0.0 tag, and no `gh` or Gitea CLI. It
also contains the uncommitted iteration work, which cannot be used as a stable
release source. Repository creation, CI secret configuration, legacy workflow
changes, Gitea artifact retrieval, and production publication require the
authenticated repository setup and a committed release source.

- Create the monorepo and six integration repositories, seed the package refs,
  configure secrets and Gitea Actions, and disable legacy publishing automation
  in the existing core repository.
- Run Gitea QA and artifact retrieval; execute the authorized first release.
- Verify seven matching tags, seven Packagist versions, fourteen GitHub release
  archives, a clean seven-package install, and continued installability of
  historical core releases. Set package default branches to `2.x`.

## Acceptance

- Gitea runs validation and retains retrievable failure diagnostics; GitHub
  remains the only publisher.
- All seven packages are published from the same release source with matching
  tags and verified Packagist references.
- A retry resumes the same release identity without moving existing tags or
  changing legacy core history.
- Historical `php-anonymizer/anonymizer` versions remain installable and the
  seven-package release installs in a clean consumer.
- Focused fixtures and the PHP 8.5 Composer QA gate pass.
