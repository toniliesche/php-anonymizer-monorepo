# Decision: Developer Tooling

## Decision

Use Composer scripts as the canonical, cross-platform developer interface.
Provide a thin GNU Make wrapper for Unix-like convenience and command
discovery. Make must only delegate to Composer; it contains no build or release
logic.

Keep non-trivial automation in versioned PHP scripts under `tools/`. CI invokes
Composer scripts directly, never Make targets.

## Required commands

The root Composer project exposes:

- `test` and `test:coverage`
- `lint` and `fix`
- `static` and `refactor:check`
- `mutation`
- `validate` and `audit`
- `qa`
- `release:preview`, `release:verify`, `release:publish`, and
  `release:packagist`
- `ci:helpers:test`, `gitea:workflows:test`, `github:workflows:test`, and the
  release fixture commands used by the QA gate

`make help` documents shortcuts for every command, including `setup`, `qa`,
and the release preview/verification commands.

## Tooling policy

- Pin development tools in the root `composer.lock`; individual library package
  manifests do not contain `version` fields or lock files.
- `composer qa` is the local and CI quality gate. It runs validation, formatting
  checks, static analysis, tests, and applicable dependency/mutation checks.
- `composer fix` is the only formatting command that mutates tracked sources.
- Developers need PHP 8.5 and Composer. The library deliberately has no Docker
  development environment and no Taskfile requirement.
- Do not install dependencies automatically from Git hooks or checkout/merge
  events. This avoids surprising network and filesystem side effects.

## References from the previous library

Retain its useful quality concepts—PHP-CS-Fixer, Rector checks, PHPStan,
Composer normalization/validation/audit, dependency analysis, PHPUnit,
coverage, Infection, and a `help` target—while removing duplicated Makefile
implementation and implicit Composer-install Git hooks.
