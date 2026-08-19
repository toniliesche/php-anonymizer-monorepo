# Refined Iteration 9.1 — Pre-v1 Cohesion and API Refactor

## Objective

Perform one final, breaking refactoring round before v1.0. Preserve the supported feature set while making the public API smaller, result types explicit, implementation classes cohesive, and quality tooling free of project-owned suppressions.

## Decisions

- Keep `AnonymizerInterface` as the only documented anonymization service contract.
- Introduce a shared typed result contract for array and object anonymization; remove the imprecise `object` return type from report-producing APIs.
- Add a small factory boundary so applications do not need to construct concrete engines or profile providers directly.
- Keep the hexagonal top-level layers (`Domain`, `Application`, `Infrastructure`) and use context-focused namespaces inside them.
- Allow breaking namespace and construction changes; do not add compatibility aliases or a second public facade.
- Keep the current supported behavior: profiles, conditions, nested arrays, payload codecs, Faker, Personas, Symfony Serializer integration, and legacy import.
- Keep extension/plugin-framework work out of this iteration.

## Implementation slices

1. **Public result and construction boundary**
   - Add `AnonymizationResultInterface` with typed data and path-report accessors.
   - Make array and object result value objects implement the contract and expose immutable accessors.
   - Add `AnonymizerFactory` methods for typed profile definitions and named profile arrays.
   - Update bundle aliases, README, migration documentation, and contract tests.

2. **Core cohesion**
   - Extract rule selection/condition evaluation, selected-value dispatch, and collection traversal from `RuleTreeTraversal`.
   - Extract schema parsing/validation responsibilities from `RuleDefinition` into focused domain collaborators.
   - Preserve path, missing-path, error, and deterministic traversal semantics with focused unit tests.

3. **Bundle wiring cohesion**
   - Split `AnonymizerExtension` into focused registrars for core services/profiles, built-ins, optional integrations, serializer wiring, and legacy import.
   - Derive reserved strategy/codec IDs from registered definitions instead of duplicating hard-coded lists.
   - Keep integration behavior and dependency errors unchanged, with direct compiler/container tests.

4. **Legacy importer cohesion**
   - Keep document reading, node/path conversion, value conversion, merging, config assembly, rendering, and atomic writing as separate units.
   - Remove project-owned complexity and coverage suppressions by adding branch-focused tests.
   - Retain only unavoidable third-party compatibility suppressions.

5. **Namespace, documentation, and quality closure**
   - Move concrete engines/adapters under context-focused implementation namespaces and update imports, Deptrac, docs, and fixtures.
   - Split broad tests into behavior-focused unit tests plus small integration contracts.
   - Require 100% published line/branch coverage, Infection MSI ≥90%, PHPStan without project suppressions, and all existing CS-Fixer, Rector, Deptrac, dependency, consumer, and release checks.
   - Update the Unreleased changelog under an Iteration 9.1 entry; after the user commits, create annotated tag `iteration-9.1`.

## Completion criteria

- The documented API consists of one anonymizer interface plus the shared result contract and factory.
- No project-owned `@codeCoverageIgnore` or complexity ignores remain in published code.
- Focused and full QA checks pass, including consumer and release-preview tests.
- Existing feature behavior is covered by contract tests and documented migration notes.
