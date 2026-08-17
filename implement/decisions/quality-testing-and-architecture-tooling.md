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
