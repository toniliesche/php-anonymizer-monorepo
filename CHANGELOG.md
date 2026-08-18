# Changelog

All notable changes to this project will be documented here.

## Iteration 4.1 — Cohesion and Context-Focused Namespace Refactor (Unreleased)

- Consolidated redaction and preservation traversal around a shared rule-tree
  engine with focused mode policies.
- Centralized JSON Pointer/report composition and separated profile reference
  validation from profile storage.
- Added focused tests for report composition and profile reference graphs.
- Kept the hexagonal Domain/Application/Infrastructure boundaries while
  grouping classes below them by profile, rule, anonymization, extension,
  traversal, policy, and validation responsibility.
- Added `Application\\Extension\\Profile`, `Payload`, and `Replacement`
  contexts instead of a generic port namespace.
- Updated tests, README examples, and internal references for the intentional
  namespace migration without changing anonymization behaviour.

## Iteration 4 — Core Extensions and JSON (Unreleased)

### Added

- Finalized enumerable profile-provider, replacement-strategy, and payload-codec
  extension ports with eager duplicate/reference/cycle validation.
- Added built-in `redact`, UTF-8 `star_mask`, and `json` implementations.
- Added top-level encoded-payload methods and embedded JSON rule processing with
  prefixed RFC 6901 report paths.

## Iteration 3 — Profile Semantics

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
