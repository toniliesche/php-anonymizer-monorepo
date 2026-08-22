# Refined Iteration 20 — Core and Framework Cohesion

## Objective

Complete the 2.0 façade migration while splitting the core execution pipeline,
profile validation, and framework wiring into focused internal collaborators.
Remove every cognitive-complexity suppression in published code without
raising the configured thresholds. Coverage-ignore cleanup remains assigned to
Iteration 21.

This iteration is behavior-preserving for supported anonymization operations,
apart from completing the already accepted 2.0 removal of obsolete capability
interfaces and direct legacy dispatchers.

## Implementation specification

### Traversal and execution context

- Add an immutable internal `TraversalExecutionContext` carrying the profile,
  current/scoped rules, global rules, traversal policy, report, execution
  budget, JSON pointer, depth, optional `AnonymizationContext`, and key-
  normalization state.
- Context derivation must be explicit for child paths, nested rule sets, and
  depth changes. Do not store mutable traversal data in the context.
- Change recursive traversal collaborators to accept the context instead of
  repeatedly passing the same execution arguments. Preserve reference-based
  updates to the current array value.
- Split `RuleTreeTraversal` into internal collaborators for rule indexing and
  selection, structural recursion, and selected-value dispatch. Preserve:
  local-over-global precedence, normalized-key matching, conditional matching,
  embedded payload traversal, scalar/list/map shape validation, report paths,
  missing paths, and execution limits.
- Keep `ArrayTraversal` responsible only for profile lookup, root validation,
  policy selection, context creation, and result assembly. Document-root map
  and list dispatch must use the same traversal context semantics as array
  roots.

### Core operation cohesion

- Split the current internal array engine into focused internal services for
  array roots, payload decode/encode, document roots, lazy records, NDJSON,
  and audit aggregation.
- Compose these services behind one internal operation aggregate consumed by
  `AnonymizerFacade`; do not expose the new collaborators as public extension
  points.
- Preserve payload codec failure wrapping, input-byte limits, document-root
  validation, record map normalization, NDJSON laziness, deterministic audit
  ordering, and the rule that NDJSON audit is available only after successful
  stream exhaustion.
- Remove obsolete capability interfaces and context-specific wrapper methods
  left over from the pre-facade API. Internal consumers, including audit
  adapters, depend only on `AnonymizerInterface` and its optional context
  argument.
- Remove obsolete direct Serializer dispatcher classes and their container
  registrations. Object anonymization continues through the documented
  object-adapter/builder path.

### Profile and rule processing

- Keep `ProfileDefinition`, `ProfileMetadata`, rule values, and other
  documented immutable values as the public contract.
- Split profile construction into internal collaborators for schema/key
  validation, metadata parsing, fragment collection/expansion, and rule/global
  rule compilation and validation.
- Preserve exact accepted profile shapes, duplicate-field behavior, fragment
  precedence, cycle diagnostics, root/mode defaults, and existing
  `InvalidProfileDefinition` messages wherever behavior is currently tested.
- Split profile-reference validation into focused strategy, transformation,
  codec/profile-reference, and embedded-profile-cycle checks.
- Refactor condition parsing/matching and temporal transformation validation/
  application into smaller helpers while preserving IDs, options, output
  types, and all failure contracts.

### Framework cohesion

- Split Symfony loading into focused internal registrars for profiles and
  limits, core builder/facade construction, optional strategy integrations,
  serializer/object adapter wiring, audit, and legacy import.
- Split Laravel registration into focused internal registrars for profiles,
  limits/core builder, optional extensions, serializer/object adapter wiring,
  and audit.
- Preserve existing configuration keys, defaults, extension tag names, and
  optional-integration diagnostics.
- Remove direct `ArrayAnonymizer`, legacy Serializer dispatcher, and other
  capability-specific public container bindings. Keep only the documented
  façade and profile-inspection bindings public; implementation definitions
  remain private/internal.
- Keep the Symfony legacy importer command available as a migration aid, but
  treat its converters, readers, renderers, and writers as internal.

### Complexity and annotations

- Remove all existing `@phpstan-ignore complexity.classLike` and
  `@phpstan-ignore complexity.functionLike` suppressions, including those in
  core traversal/profile code, framework registrars, legacy rule conversion,
  condition matching, and built-in temporal transformations.
- Do not remove unrelated `@codeCoverageIgnore` or non-complex PHPStan
  suppressions in this iteration; those are reviewed with their replacement
  tests in Iteration 21.
- Mark every newly extracted implementation and registrar `@internal`.

### Documentation and recorded decisions

- Update `docs/api-surface-v2.md`, migration references, and affected package
  changelogs to reflect the completed capability-interface and direct-dispatch
  removal.
- Keep the extensive Extension Guide and User Guide work in their assigned
  later iterations.
- Do not add new public symbols without an API-surface entry, contract test,
  and migration/changelog note.

## Tests and acceptance criteria

- Add traversal regression tests for scoped/global precedence, conditions,
  normalized keys, nested maps/lists, embedded payloads, reports, missing
  paths, limits, and invalid shapes.
- Add profile tests for schema/metadata/fragments, duplicate and cycle
  diagnostics, global rules, and rule-reference validation.
- Add façade contract tests for arrays, payloads, documents, records, audit,
  NDJSON laziness/exhaustion, contextual calls, and missing object adapters.
- Update Symfony and Laravel tests to verify preserved configuration/tag
  behavior, façade-only public bindings, registrar composition, and optional
  module failure paths.
- Add legacy importer, condition, and temporal transformation tests covering
  each extracted branch and unchanged diagnostics.
- Verify published source contains no cognitive-complexity suppression.
- Run focused tests followed by:

  ```bash
  PHP_VERSION=8.5 composer test
  PHP_VERSION=8.5 composer test:coverage
  PHP_VERSION=8.5 composer static
  PHP_VERSION=8.5 composer lint
  PHP_VERSION=8.5 composer check
  PHP_VERSION=8.5 composer qa
  ```

- Run all relevant consumer tests, release-preview validation, `git diff
  --check`, and `graphify update .` after code changes. Generated Graphify
  output remains uncommitted.

## Fixed decisions and assumptions

- All current cognitive-complexity suppressions are in scope for Iteration 20.
- Coverage-ignore removal and the broad internal-boundary audit remain in
  Iteration 21.
- Framework configuration and extension tag names remain compatible; direct
  engine and legacy-dispatcher bindings do not.
- The unified `AnonymizerInterface` is the sole anonymization service contract
  in 2.0. Removed capability interfaces and legacy Serializer dispatchers do
  not receive deprecation aliases.
- All extracted classes are implementation details and carry no semver
  guarantee unless explicitly listed in the API-surface document.
