# Decision: Quality, Testing, and Architecture Tooling

## Decision

Use a focused toolchain with one primary responsibility per tool. Every pull
request runs the complete quality suite, including mutation testing and
clean-consumer end-to-end tests. Do not use quality baselines or blanket
suppressions to make the suite pass.

## Selected tools

| Tool | Responsibility |
| --- | --- |
| PHP-CS-Fixer | PSR-12-based style with explicit project rules |
| PHPUnit | Unit, integration, extension-contract, and end-to-end tests |
| PHPStan | Maximum-level type analysis with strict rules |
| Composer Dependency Analyser | Correct production and development dependency declarations |
| Deptrac | Package and hexagonal-architecture dependency boundaries |
| Rector | Pinned, reviewable modernization rules |
| Infection | Mutation testing for the core and extension contracts |
| Composer Normalize | Deterministic Composer metadata |
| Composer validate/audit | Composer metadata validity and vulnerability checks |

Do not add Psalm, Pest, PHPMD, or a second dependency checker in v1. They
overlap with the selected tools without providing a distinct enough benefit for
this project.

## Architecture rules

- The core domain depends only on PHP.
- The core application layer depends on the domain and owns the port
  interfaces.
- Core infrastructure implements those ports.
- The Bundle, Faker, and Serializer packages may depend on the core; the core
  never depends on optional packages.
- Symfony dependencies are limited to the Bundle and Serializer packages;
  FakerPHP is limited to the Faker package.

Deptrac enforces these directions. PHPStan uses strict rules and PHPUnit support
globally, with Symfony-specific analysis enabled only in Symfony packages.

## Commands and enforcement

Composer scripts are canonical; Make aliases only delegate:

- `test`, `test:coverage`, `lint`, `fix`, `static`, `architecture`,
  `dependencies`, `refactor:check`, `rector:fix`, `mutation`, and `validate`
- `check` runs every non-mutating check except mutation testing.
- `qa` runs `check`, Infection, and clean-consumer package/end-to-end tests.

`composer fix` and `composer rector:fix` are the only intentionally mutating
quality commands. CI runs `composer qa` on PHP 8.5 for every pull request,
release-branch merge, and release commit, and stores coverage and Infection
reports as artifacts.

## Monorepo configuration model

The root owns orchestration, shared policy, and the lock file. Root Composer
scripts invoke each tool once, and root configuration defines defaults that are
valid for every package. They do not make optional integrations runtime
dependencies of the core.

- The core package is always included in root PHPUnit, PHPStan, CS-Fixer,
  Rector, dependency-analysis, and Deptrac paths.
- Later packages add their own source and tests to the relevant root tool paths
  and add package-specific configuration only where framework knowledge is
  required. Symfony rules stay scoped to the Bundle/Serializer packages; Faker
  rules stay scoped to the Faker package.
- PHPUnit uses one root test configuration with separate suites and shared
  strict flags. Package tests declare their own coverage metadata, while the
  core coverage threshold is checked separately from the aggregate project
  threshold.
- PHPStan uses the root maximum-level configuration plus package-specific
  includes for Symfony support when those packages arrive. No package is
  allowed to lower the root level or introduce a baseline.
- Deptrac layers remain package-aware: core Domain/Application/Infrastructure
  boundaries are mandatory, and optional packages are collected as outer layers
  that may depend inward but are never imported by the core.
- Composer Dependency Analyser validates each package manifest in the same run;
  package production dependencies are checked separately from root-only tools.
- Infection targets the core and extension-contract tests first, then expands
  its source directories as implementations arrive. Thresholds remain global.

This keeps one predictable `composer qa` entry point while preventing Symfony,
Faker, YAML, or serializer concerns from leaking into the pure core.

## Composer metadata per package

Composer treats the root manifest as the active project by default; it does not
recursively normalize or validate `packages/*/composer.json`. The quality gate
therefore runs both levels explicitly:

- Root: `composer normalize --dry-run` and `composer validate --strict`, with
  the root `composer.lock` checked and committed.
- Each package: `composer normalize --dry-run --no-update-lock --no-check-lock
  packages/<name>/composer.json` and
  `composer validate --strict --no-check-lock --working-dir=packages/<name>`.
- Package manifests never gain lock files or tracked `version` fields. Their
  publishability, PSR-4 metadata, package type, PHP constraint, license, and
  runtime dependency declarations are validated independently.

The root `validate:metadata` command and `composer qa` must invoke this
package loop in a cross-platform PHP helper or an equivalent Composer-script
sequence. A successful root validation alone is insufficient for a green
monorepo quality gate.

## Test policy

- Require 100% line and branch coverage in the core.
- Require 95% line coverage and a 90% covered mutation score across the full
  project before `1.0.0`.
- Core tests require `#[CoversClass]` or `#[CoversNothing]`; PHPUnit fails on
  risky tests, warnings, unexpected output, notices, and deprecations.
- Keep tests deterministic: fixed Faker seeds, no time/network dependencies,
  and fixtures rather than snapshots unless importer output needs reviewed
  snapshots.

## References from the previous library

The previous library already demonstrates PHP-CS-Fixer, PHPUnit, strict
PHPStan, Rector, Composer Dependency Analyser, Composer Normalize, audit,
coverage, and Infection. Retain those strengths, remove the unused PHPMD path,
and add Deptrac to enforce the architecture that the rewrite introduces.

## Implemented quality-policy refinements

The workspace uses PHPStan's extension installer for strict, deprecation,
PHPUnit, and thecodingmachine Safe rules. Safe is a direct workspace
dependency because the Rector migration set is an explicit tooling contract;
packages declare Safe directly only when their published source imports it.

Published package `src/` and `tests/` are analysed recursively at maximum
level. A first-party PHPStan rule rejects dangerous process/debug/evaluation
functions and single-byte string functions, and requires supported `mb_*`
calls to pass the literal `UTF-8` encoding. It scopes itself to `packages/`,
so release and build tooling can retain necessary process APIs. This local
rule is used because the commonly available disallowed-calls extension is not
compatible with PHPStan 2.

PHPUnit uses the 13.3 schema, recursive package test discovery, strict issue
and coverage handling, and fails on empty, incomplete, skipped, risky, or
deprecated tests. Rector runs shared PHP 8.5/code-quality sets plus a separate
Safe migration configuration limited to package code. CS-Fixer uses the
current PER-CS3 rules and stores its cache below `var/`.
