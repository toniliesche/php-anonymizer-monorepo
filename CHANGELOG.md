# Changelog

All notable changes to this project will be documented here.

## Iteration 3 — Profile Semantics (Unreleased)

### Added

- Preserve-mode allow-list traversal for recursively redacting unselected
  strings.
- Strict `when` predicates and explicit `on_scalar` actions for mixed shapes.

## Iteration 2 — Core Traversal and Diagnostics

### Added

- Recursive `children` and `each` traversal for nested array profiles.
- Immutable anonymization reports with changed and missing JSON Pointer paths.
- Strict runtime shape validation for maps, lists, and selected string values.

## Iteration 1 — Foundation and Minimal Core

### Added

- Initial hexagonal core and development workspace foundation.
- Flat, schema-first array anonymization with immutable profile definitions.
- Independently installable package manifests and release artifacts.
- Composer, PHPUnit 13, PHPStan, PHP-CS-Fixer, Rector, Deptrac, Infection,
  dependency analysis, consumer-install, and release-preview quality gates.
- Package-scoped Safe and UTF-8 string-function policies.
