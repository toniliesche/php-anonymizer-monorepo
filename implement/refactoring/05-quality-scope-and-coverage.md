# Iteration 27: Quality Scope and Non-Vacuous Coverage Gates

## Status

Implemented with a line-only coverage gate. Branch/path enforcement is
deferred because the installed PHP 8.5.9/Xdebug 3.5.3 runtime exits with status
139 during path instrumentation.

## Goal

Make all seven published packages participate in PHPUnit and Infection scope,
prevent quality-configuration drift, and make coverage reports prove positive
100% line coverage without changing library APIs or application
runtime behavior.

## Quality-scope enforcement

- Add `composer quality:scope` and run it from `composer check`.
- Discover publishable packages from `packages/*/composer.json` entries with a
  `src/` directory; this currently yields the seven Anonymizer packages.
- Parse `phpunit.xml` and `infection.json5`, normalize paths relative to the
  repository root, and require both configurations to contain exactly the
  discovered source-directory set. Fail on omissions, duplicates, or unknown
  paths with a package-specific diagnostic.
- Reject Infection `source.excludes` and broad mutation-ignore annotations.
  Permit only justified `@infection-ignore-next-line` annotations with an
  explanation on the same line.
- Keep the check deterministic and repository-local; do not introduce a
  second manually maintained package manifest.

## Mutation scope

- Add `packages/anonymizer-laravel/src` and
  `packages/anonymizer-pseudonymization/src` to Infection.
- Keep the existing minimum covered MSI of 90%.
- Do not add or remove an Infection timeout: no timeout is currently
  configured. Remove any timeout if one is introduced before implementation.
- Run mutation testing across all seven source roots and preserve the existing
  mutation semantics and suppression policy.

## Coverage report and driver

- Change `tools/run-coverage.php` to emit Cobertura XML at `var/coverage.xml`.
- Configure PHPUnit for line coverage only until a compatible branch/path
  driver is available. Keep the checker strict about positive line totals.
- Update `tools/check-coverage.php` to parse Cobertura root attributes and
  reject missing, malformed, zero, partial, or non-100% line metrics.
- Track a compatible PHP/Xdebug combination for a future branch/path coverage
  job; do not treat absent branch data as coverage.
- Keep ordinary `composer test` and production traversal code unchanged; the
  coverage-only driver settings are limited to QA runs.
- Disable CLI OPcache/JIT in `tools/run-coverage.php`; coverage instrumentation
  must use a non-JIT runtime to avoid engine/instrumentation crashes, while
  production and ordinary test execution retain their normal settings.

## Exclusion review and tests

- Review every production `@codeCoverageIgnore*` and mutation suppression.
- Add a quality-exclusions document listing each retained exclusion, its
  framework-wiring/immutable-plumbing/optional-integration/defensive-path
  reason, and the test or boundary that protects it.
- Remove exclusions where direct behavior tests provide meaningful coverage,
  beginning with Laravel YAML mapping-key validation and pseudonymization date
  shifting.
- Add pseudonymization tests for deterministic string and `DateTimeInterface`
  shifts, option validation, invalid dates, missing scope, unavailable/empty
  keys, and non-contextual invocation.
- Extend Laravel Testbench tests only for exclusions removed from framework
  wiring; retain documented container-only exclusions.
- Add tool-level tests or fixture runs for quality-scope omissions,
  duplicates, unknown paths, Infection exclusions, broad suppression syntax,
  and Cobertura missing/zero/partial/malformed metrics.

## Acceptance criteria

- `PHP_VERSION=8.5 composer quality:scope` passes and detects intentional
  configuration drift in negative fixtures.
- PHPUnit and Infection each contain all seven package source roots.
- Cobertura reports positive totals with 100% covered lines.
- Infection reaches covered MSI >= 90% across all seven packages.
- `PHP_VERSION=8.5 composer check`, `test:coverage`, `mutation`, and
  `git diff --check` pass; branch/path coverage remains a documented follow-up.
- Refresh Graphify after implementation; generated Graphify output remains
  uncommitted.

## Scope and release notes

- No public PHP API or anonymization behavior changes are allowed.
- Update the root Unreleased changelog and this plan; leave package
  changelogs unchanged unless implementation changes public package behavior.
