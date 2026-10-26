# Repository Guidelines

## Project Structure & Module Organization

This PHP 8.5+ Composer monorepo contains the PHP Anonymizer packages. Source is
under `packages/*/src`, package tests under `packages/*/tests`, and workspace
tests under `tests/`. Documentation is in `docs/`, release and validation tools
in `tools/`, and architecture plans in `implement/`. Keep changes within the
relevant package and update its README or changelog for public changes.

## Build, Test, and Development Commands

Install dependencies with `composer install` or `make setup`. The full quality
gate is `composer qa`; focused commands include:

- `composer test` — run PHPUnit suites.
- `composer test:coverage` — run the published-package coverage gate; coverage
  must remain 100% and tests must not be risky, skipped, incomplete, warning,
  deprecated, or otherwise flagged.
- `composer lint` / `composer fix` — check or apply PHP-CS-Fixer formatting.
- `composer static` — run maximum-level PHPStan.
- `composer refactor:check` — verify Rector changes are clean.
- `composer check` — run the main checks without the extended QA steps.

Use PHP 8.5 explicitly when invoking project commands so local and CI behavior
match, for example `PHP_VERSION=8.5 composer test` or
`PHP_VERSION=8.5 composer qa`. Run focused tests first, then the full QA gate
for substantial changes. Consumer smoke tests and Graphify refreshes are part
of release validation when their related code changes.

## Coding Style & Naming Conventions

Follow `.editorconfig`: four spaces for PHP, two for JSON/YAML, LF endings, and
final newlines. Use PSR-4 namespaces, PascalCase classes, camelCase
methods/properties, and `*Test.php` filenames. Keep core components typed and
framework-independent; preserve package boundaries. Prefer immutable/read-only
value objects where the domain contract permits them, use `#[Override]` on
interface and parent overrides, and keep public APIs documented with precise
iterable/value types. Run `PHP_VERSION=8.5 composer lint` after edits; do not
hand-format around the configured fixer rules.

## Testing Guidelines

Add or update PHPUnit tests for behavior changes. Coverage metadata is required,
and skipped, risky, incomplete, warning, or deprecated tests fail. Use unit
tests for package logic and integration tests for Symfony wiring. Run
`PHP_VERSION=8.5 composer test`, then
`PHP_VERSION=8.5 composer test:coverage` and
`PHP_VERSION=8.5 composer qa` for substantial changes. Preserve 100% published
package line and branch coverage; when a test exercises an indirect class,
declare it with the appropriate PHPUnit `#[UsesClass]`/`#[CoversClass]`
attribute rather than weakening the coverage configuration.

## Commit & Pull Request Guidelines

Use concise imperative Conventional Commit subjects such as `feat: ...`,
`refactor: ...`, or `fix: ...`. Pull requests should explain the change, list
affected packages, link issues or plans, and include tests and documentation
updates. Mention release or migration impact; add screenshots for visual docs.

## Release Changelog Hygiene

The repository uses a synchronized release train. The current work-in-progress
release is `2.0.1`; update the root changelog and only the package
`CHANGELOG.md` files affected by the change. Add package-specific entries where
behavior changed; leave unrelated package changelogs untouched. Keep an empty
`Unreleased` section above the current release for subsequent work.

## Repository Automation

The Graphify knowledge graph in `graphify-out/` maps cross-file relationships.
For codebase questions, query it first when `graphify-out/graph.json` exists:
use `graphify query "..."`, `graphify path "A" "B"`, or
`graphify explain "Concept"`; the Make equivalent is
`make graphify-query QUESTION="..."`. Use `graphify-out/wiki/index.md` for
broad navigation and read `GRAPH_REPORT.md` only for broad reviews or when
focused queries are insufficient.

When a user types `/graphify`, follow the installed Graphify skill or
instructions before other work. Dirty Graphify output is expected and is not a
reason to skip it unless the task concerns stale or incorrect graph data, or
the user explicitly opts out. After modifying code, run `graphify update .` to
refresh the graph. Do not commit generated caches or local analysis output.
