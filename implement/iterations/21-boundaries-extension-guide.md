# Iteration 21 — Verified Boundaries and Extension Guidance

## Status

Completed.

## Goal

Complete the 2.0 public-boundary cleanup, selectively remove project-owned
published-code suppressions where stronger tests or safer typing provide
concrete value, and provide contract-complete extension documentation.

## Current direction

- Review coverage, PHPStan, and Infection suppressions individually; remove
  only those whose replacement improves behavioral confidence or type safety.
- Removed the high-value suppressions around JSON root validation,
  transformation registration, and transformation-option compilation; added
  focused invalid-input tests and retained the JSON Pointer equivalent mutant
  ignore.
- Retain justified exclusions for framework wiring, immutable plumbing,
  optional integrations, impossible defensive paths, and equivalent mutants.
- Marked non-public production symbols with `@internal` for IDE and
  documentation guidance, while architecture checks use the API manifest and
  explicit package/namespace rules for dependencies and public examples.
- Removed the legacy factory and Serializer dispatcher classes, migrating
  callers to the builder and object adapter.
- Added framework extension discovery and documented module ordering,
  collisions, stable IDs, contexts, errors, compatibility, and contract tests.
- Added a Composer public-example boundary check based on the documented API
  manifest and removed the legacy factory and Serializer dispatcher symbols.
- Reworked the extension guide, README claims, API/migration references, and
  affected changelogs.

## Acceptance

- The documented API manifest is the exact public boundary; `@internal` is not
  used as an enforcement signal.
- Published source retains 100% configured coverage; each removed suppression
  has focused verification, while retained suppressions have documented
  reasons. Framework modules, clean-break removals, consumers, and
  architecture checks pass.

The implementation specification is [Refined Iteration 21 — Verified
boundaries and extension guidance](../refined-iteration-21-boundaries-extension-guide-plan.md).
