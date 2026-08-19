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
- `composer lint` / `composer fix` — check or apply PHP-CS-Fixer formatting.
- `composer static` — run maximum-level PHPStan.
- `composer refactor:check` — verify Rector changes are clean.
- `composer check` — run the main checks without the extended QA steps.

## Coding Style & Naming Conventions

Follow `.editorconfig`: four spaces for PHP, two for JSON/YAML, LF endings, and
final newlines. Use PSR-4 namespaces, PascalCase classes, camelCase
methods/properties, and `*Test.php` filenames. Keep core components typed and
framework-independent; preserve package boundaries.

## Testing Guidelines

Add or update PHPUnit tests for behavior changes. Coverage metadata is required,
and skipped, risky, incomplete, warning, or deprecated tests fail. Use unit
tests for package logic and integration tests for Symfony wiring. Run
`composer test`, then `composer qa` for substantial changes.

## Commit & Pull Request Guidelines

Use concise imperative Conventional Commit subjects such as `feat: ...`,
`refactor: ...`, or `fix: ...`. Pull requests should explain the change, list
affected packages, link issues or plans, and include tests and documentation
updates. Mention release or migration impact; add screenshots for visual docs.

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
