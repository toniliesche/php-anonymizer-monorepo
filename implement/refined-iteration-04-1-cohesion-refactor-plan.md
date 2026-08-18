# Refined Iteration 4.1 — Cohesion and Context-Focused Namespace Refactor

## Goal

Improve the core’s internal cohesion and organise namespaces by responsibility
before the Symfony Bundle MVP without changing the stable anonymizer
interfaces, profile schema, output data, report paths, or exception semantics.

## Implemented architecture

- `RuleTreeTraversal` owns rule selection, conditions, missing paths, shape
  validation, collection recursion, and embedded-payload routing.
- `RedactTraversalPolicy` and `PreserveTraversalPolicy` contain the mode-specific
  leaf and unselected-value behavior.
- `JsonPointer` centralizes RFC 6901 escaping and nested path composition;
  `AnonymizationReport` owns changed/missing path accumulation and merging.
- `ProfileRegistry` now only stores and enumerates profiles.
- `ProfileReferenceValidator` validates strategy, codec, and profile references;
  `EmbeddedProfileReferenceGraph` detects deterministic reference cycles.
- The previous mode-specific traversal classes are removed as implementation
  details. `AnonymizerInterface` and extension ports remain unchanged.

## Context-focused namespaces

Keep the hexagonal top-level layers `Domain`, `Application`, and
`Infrastructure`, while grouping classes below them by responsibility:

- Domain profiles live under `Domain\Profile`; rules live under `Domain\Rule`,
  with nested `Condition`, `Payload`, and `Compiler` contexts.
- Application orchestration lives under `Application\Anonymization`, with
  nested `Traversal`, `Policy`, `Path`, and `Result` contexts.
- Replaceable extension points share `Application\Extension`, with focused
  `Profile`, `Payload`, and `Replacement` contexts for interfaces, registries,
  validation, and built-ins.
- Infrastructure remains reserved for external adapters such as the in-memory
  profile provider.

This is an intentional namespace-only breaking change. Built-in defaults,
constructor semantics, extension contracts, and runtime behaviour remain
unchanged. Tests and user-facing documentation mirror the new namespaces.

## Test organization

- Existing façade behavior remains covered for redaction, preservation, nested
  values, conditions, payloads, and failures.
- New focused tests cover JSON-pointer/report composition and profile-reference
  validation/cycle detection.
- Coverage metadata is updated to target the new traversal and validation units.

## Acceptance criteria

- All existing behavioral assertions remain green, including exact JSON Pointer
  paths and contextual codec/profile failures.
- PHPUnit, PHPStan, CS-Fixer, Rector, Deptrac, dependency/metadata validation,
  coverage, and Infection pass with the repository’s configured thresholds.
- This remains an internal refactor and does not add a compatibility layer for
  removed concrete traversal classes.
- The cohesion and namespace changes are one milestone identified as
  `iteration-4.1`.
