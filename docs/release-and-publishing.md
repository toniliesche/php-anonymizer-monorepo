# Release and package publishing

The workspace is the development monorepo for seven Composer packages. Gitea
runs validation and stores CI diagnostics. GitHub Actions is the only release
publisher. A release uses one source commit and one stable SemVer version for
all seven packages.

## Package repositories and Packagist

Keep the existing core repository and Packagist registration so its published
history remains available. Create the six integration repositories as empty
public repositories under the `toniliesche` GitHub organization. Do not
initialize them with a README, license, or first commit; the first release
pushes the split history and `2.x` branch. Register each repository on
Packagist with its matching package name.

| Composer package | GitHub repository | Registration |
| --- | --- | --- |
| `php-anonymizer/anonymizer` | `toniliesche/php-anonymizer` | Existing; retain |
| `php-anonymizer/anonymizer-bundle` | `toniliesche/php-anonymizer-bundle` | New |
| `php-anonymizer/anonymizer-faker` | `toniliesche/php-anonymizer-faker` | New |
| `php-anonymizer/anonymizer-laravel` | `toniliesche/php-anonymizer-laravel` | New |
| `php-anonymizer/anonymizer-personas` | `toniliesche/php-anonymizer-personas` | New |
| `php-anonymizer/anonymizer-pseudonymization` | `toniliesche/php-anonymizer-pseudonymization` | New |
| `php-anonymizer/anonymizer-symfony-serializer` | `toniliesche/php-anonymizer-symfony-serializer` | New |

Enable Packagist's GitHub update hook for each registration. The publisher also
requests an update through the Packagist API and waits for every package version
and source reference to appear before marking the GitHub release as published.

## CI setup and permissions

Configure repository or organization secrets before enabling the workflows:

| Secret | Platform | Purpose |
| --- | --- | --- |
| `CI_REGISTRY_USERNAME` | Gitea | Pull the private PHP CI builder image |
| `CI_REGISTRY_PASSWORD` | Gitea | Pull the private PHP CI builder image |
| `PACKAGE_PUBLISH_TOKEN` | GitHub | Push package branches and annotated tags to all six integration repositories and the core repository |
| `PACKAGIST_USERNAME` | GitHub | Authenticate Packagist update requests |
| `PACKAGIST_API_TOKEN` | GitHub | Authenticate Packagist update requests |

Use a GitHub fine-grained token for `PACKAGE_PUBLISH_TOKEN`, restricted to the
seven package repositories with repository contents read/write access. The
workflow's built-in `GITHUB_TOKEN` is separately limited to contents and pull
request write permissions in the release job; it creates the draft/release,
pushes the monorepo tag, and opens or reuses the synchronization pull request.
Publishing secrets are injected only into the release publication step.

GitHub quality, preview, and benchmark jobs use `contents: read`. The release
workflow grants its release job `contents: write` and `pull-requests: write`,
checks out the exact merged commit, and runs full QA and a publication dry-run
before using any publishing secrets. It serializes release runs so two releases
cannot publish concurrently.

Gitea workflows need the registry credentials to pull the private builder.
They restrict that image access on pull requests to same-repository events.
Gitea does not receive package publishing or Packagist credentials.

### Local GitHub authentication

For ordinary local GitHub CLI use, run `gh auth login --hostname github.com`
and complete the browser flow. `gh` stores the login in the system credential
store when one is available. Check it with `gh auth status`; never use
`gh auth status --show-token` in shared logs. GitHub CLI environment tokens
take precedence over stored credentials, so clear an obsolete `GITHUB_TOKEN`
or `GH_TOKEN` if `gh auth status` reports that an environment token is invalid.

For local release execution, the release command reads `GITHUB_TOKEN` (or
`GH_TOKEN`) for the monorepo's GitHub release/tag/PR operations, and
`PACKAGE_PUBLISH_TOKEN` for package repository pushes. Prefer two expiring
fine-grained personal access tokens with access limited to the required
repositories:

- The GitHub API token: select `php-anonymizer-monorepo` and grant repository
  metadata read, contents read/write, and pull requests read/write.
- The package push token: select the existing `php-anonymizer` repository and
  the six integration repositories, and grant repository metadata read and
  contents read/write.

Create these under GitHub Settings → Developer settings → Personal access
tokens → Fine-grained tokens. Select `toniliesche` as the resource owner; an
organization may require an owner to approve the token. Load tokens into the
current shell from a local secret manager or a non-echoing prompt. Do not put a
token in source files, shell command arguments, CI logs, or chat. Run
`composer release:publish` first to inspect its dry-run; only pass `--execute`
when you intend to publish or resume the selected release. The automated
workflow uses its built-in `GITHUB_TOKEN` and the `PACKAGE_PUBLISH_TOKEN`
repository secret instead of a local personal token.

## First release setup

1. Create the six empty GitHub repositories in the table and register the six
   integration packages on Packagist. Preserve the existing core repository
   and its Packagist registration.
2. Add the Gitea registry and GitHub publishing secrets listed above. Confirm
   the GitHub Actions workflow permissions allow the release job to write
   contents and pull requests.
3. Push the monorepo's `develop` and `main` branches and full tag history to its
   GitHub origin. Set `main` as the default branch; keep integration package
   repository default branches as configured until the first release creates
   the corresponding `2.x` branches.
4. Run the Gitea runtime-check workflow and quality workflow. Confirm that the
   workflow artifacts can be downloaded and include diagnostics.
5. Create `release/2.0.x` from `develop`, complete release preparation, and
   open a pull request to `main`. The preview reports the next version from
   reachable tags; the quality workflow runs the full QA gate.

The release tooling refuses branch regressions, conflicting tags, incomplete
history, mismatched release sources, and package manifests that are not ready
for the requested version. Do not manually seed package branches or tags.

## Preview and release

On a release branch, fetch tags and inspect the plan with:

```sh
git fetch --tags origin
git switch release/2.0.x
composer release:preview
```

`composer release:publish` prints a dry-run plan by default. It checks release
readiness and all package destinations without publishing. The normal release
path is to merge the release pull request into `main`; the GitHub release
workflow checks out that merge commit, verifies it is reachable from `main`,
runs QA, prints the dry-run, then publishes all seven packages and verifies
Packagist and a clean consumer installation.

The workflow creates the same unprefixed version tag in the monorepo and every
package repository. The six new package repositories receive a `2.x` branch on
their first publication. After that publication, set `2.x` as each new
repository's default branch.

## Recovery

If a release workflow fails while the GitHub release is still a draft, use
GitHub Actions → Release → Run workflow. Supply all three values from the
original release identity:

- **Release version:** the exact stable version already planned or drafted.
- **Source SHA:** the full merge commit SHA used by the original workflow run.
- **Release branch:** the matching `release/X.Y.x` branch.

The workflow fetches full history and tags, checks that source SHA against
`origin/main`, reruns QA and preflight, and resumes from the draft release's
saved manifest and assets. Reuse the same version, SHA, and branch; the saved
manifest is the release identity. The workflow refuses a conflicting tag,
changed manifest, or already-published release. If publication has completed
but the final synchronization pull request could not be opened, open that pull
request from `main` to `develop` directly.

For diagnosis, download the `release-<run-id>-<attempt>` artifact from the
workflow run. It contains per-gate logs, available coverage and mutation
summaries, and workflow metadata. The GitHub and Gitea validation workflows
retain their diagnostics in similarly named run artifacts.

## Maintainer commands

The root Composer scripts are the canonical interface:

```sh
PHP_VERSION=8.5 composer qa
PHP_VERSION=8.5 composer gitea:workflows:test
PHP_VERSION=8.5 composer github:workflows:test
PHP_VERSION=8.5 composer release:publish
```

`composer release:packagist -- <release-manifest.json>` requests Packagist
updates, verifies all seven source references, and installs the exact release
in a clean consumer. It requires `PACKAGIST_USERNAME` and
`PACKAGIST_API_TOKEN`; the automated release orchestrator runs this step as
part of publication.
