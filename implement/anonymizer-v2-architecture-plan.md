# PHP Anonymizer 2.0 Architecture and Cleanup Plan

This plan prepares the synchronized PHP Anonymizer packages for a clean
`2.0.0` release. Work is intentionally split into iterations; each iteration
must leave the affected packages installable, documented, and covered by the
quality gate before the next begins.

## Baseline findings

- The core `AnonymizerInterface` advertises `array|object`, while the core
  engine is array-only and object support lives in the optional Serializer
  package.
- The concrete engine implements eight capability interfaces, causing callers
  and framework integrations to perform capability-specific wiring and
  runtime checks.
- `AnonymizerFactory` and `ArrayAnonymizer` construction currently pass five
  extension/configuration arguments; lower-level traversal methods pass even
  larger state sets.
- Traversal, profile parsing/composition, validation, façade orchestration,
  Symfony wiring, and Laravel wiring contain complexity suppressions.
- Published source currently contains project-owned PHPStan and coverage
  exemptions, while only `ProfileCompositionCompiler` is explicitly marked
  `@internal`.
- Documentation has profile and extension references, but no task-oriented
  user guide; optional package READMEs are minimal.

The Graphify report is a useful navigation aid, but it must be refreshed at
the start of implementation because the checked-in report predates the latest
Laravel commit.

## Iteration 18 — Public-surface inventory and 2.0 decisions

- Refresh Graphify and inventory every autoloaded class, interface, exception,
  value object, and framework entry point across all packages.
- Classify each symbol as supported API, extension port, value/result type,
  framework entry point, or internal implementation.
- Record the exact v1-to-v2 replacement/removal table and semver guarantees in
  an ADR and a refined iteration plan.
- Keep the Symfony legacy importer in 2.0 as a migration aid, deprecate it for
  removal in 3.0, and mark its converters, readers, renderers, and writers
  internal.
- Adopt a clean 2.0 break: no compatibility aliases or deprecated factory
  shims for replaced APIs.

The refined execution record is [Iteration 18 — Public-Surface Inventory and
2.0 API Policy](refined-iteration-18-public-api-policy-plan.md). Its inventory,
binding policy, and developer-facing API index are recorded in
[the iteration record](iterations/18-public-surface-inventory.md),
[the v2 public API decision](decisions/v2-public-api-policy.md), and
[`docs/api-surface-v2.md`](../docs/api-surface-v2.md).

## Iteration 19 — Unified façade, fluent builder, and extension contracts

The completed iteration record is [Iteration 19 — Unified façade, immutable
builder, and extension modules](iterations/19-unified-facade-builder.md). The
implementation-ready specification is [Refined Iteration 19 — Unified
façade, immutable builder, and extension modules](refined-iteration-19-unified-facade-builder-plan.md).

- Retain one generic `AnonymizerInterface` for array and object callers and
  make all supported payload, document, batch, stream, audit, and contextual
  operations available through that façade.
- Replace the multi-argument factory/engine construction path with a fluent
  `AnonymizerBuilder` that registers profiles/providers, strategies, codecs,
  transformations, limits, modules, and an optional object adapter before
  `build()`.
- Add `ObjectAnonymizerInterface` as the explicit object extension port. The
  Serializer package supplies the adapter; an absent adapter produces a clear
  unsupported-capability exception.
- Add `AnonymizerExtensionInterface` so extension packages can register a
  coherent module rather than requiring several unrelated registration paths.
- Make `ArrayAnonymizer` an internal engine behind the façade, and update the
  Symfony and Laravel integrations so all public aliases resolve to the same
  complete façade.

## Iteration 20 — Core and framework cohesion

The completed iteration record is [Iteration 20 — Core and framework
cohesion](iterations/20-core-framework-cohesion.md). The implementation-ready
specification is [Refined Iteration 20 — Core and Framework Cohesion](refined-iteration-20-core-framework-cohesion-plan.md).

- Introduce an internal traversal/execution context carrying profile, rules,
  policy, report, budget, pointer, depth, context attributes, global rules,
  and key-normalization state.
- Split `RuleTreeTraversal` into rule indexing/selection, recursive structural
  traversal, and selected-value dispatch while preserving precedence, report
  paths, limits, and lazy stream semantics.
- Split profile compilation into focused schema, metadata, fragment, and rule
  validation collaborators; keep only immutable public profile value objects
  and provider contracts exposed.
- Extract payload/document/NDJSON/audit operations from the internal engine.
- Split Symfony extension loading and Laravel provider registration into
  focused registrars.
- Remove complexity suppressions through extraction and simpler control flow;
  do not raise the configured cognitive-complexity thresholds.

## Iteration 21 — Coverage policy, internal boundaries, and extension guide

The completed iteration record is [Iteration 21 — Verified boundaries and
extension guidance](iterations/21-boundaries-extension-guide.md). The
implementation-ready specification is [Refined Iteration 21 — Verified
boundaries and extension guidance](refined-iteration-21-boundaries-extension-guide-plan.md).

- Review project-owned `@codeCoverageIgnore`, `@phpstan-ignore`, and related
  mutation exemptions individually; remove only those where focused branch,
  diagnostic, failure-path, or mutation tests provide concrete value, or
  where a PHPStan error can be resolved with safer typing.
- Retain justified suppressions for framework wiring, immutable plumbing,
  optional integrations, impossible defensive paths, vendor contracts, and
  behaviorally equivalent mutants.
- Mark registries, validators, traversal/policy classes, parsers/compilers,
  concrete built-ins, framework wiring helpers, and legacy-import internals
  with `@internal` for IDE/documentation guidance only.
- Preserve semver guarantees for the documented façade, extension ports,
  profile/value/result types, and catchable public exceptions.
- Keep Deptrac package/namespace rules straightforward and independent of
  `@internal`; add only the minimal rule needed for supported extension
  package boundaries. Use the API manifest in a separate small check for
  implementation symbols in public documentation and examples.
- Rewrite `docs/extension-author-guide.md` around the builder/module model,
  stable IDs, every extension port, contextual execution, framework
  registration, error contracts, compatibility rules, and contract tests.

## Iteration 22 — Structural code cleanup and PSR-4 compliance

The implementation outline is [Iteration 22 — Structural code cleanup and
PSR-4 compliance](iterations/22-structural-code-cleanup.md).

- Enforce one named symbol per PSR-4-matching file across production and tests.
- Replace anonymous test doubles and deferred callback arguments with named
  implementations, retaining small `array_*` callbacks and framework-required
  closures as explicit exceptions.
- Rework internal role-oriented namespaces and test support folders without
  changing established public application/domain namespaces.
- Review `@var` annotations, use Webmozart Assert only where existing
  validation contracts are preserved, and apply non-behavioral style cleanup.

## Iteration 23 — User guide, migration, and release closure

- Add `docs/user-guide.md` with real-world examples for plain PHP setup,
  redact/preserve profiles, nested data and conditions, strategies and typed
  transformations, embedded JSON/YAML, deterministic pseudonyms, limits and
  audit, batches and NDJSON, Symfony objects, and Laravel configuration.
- Keep root and package READMEs concise and link to the user guide, extension
  guide, profile reference, and migration documentation.
- Add `docs/migration-v2.md` covering builder migration, façade/interface
  changes, internalization, framework rebinding, and legacy-import
  deprecation.
- Update the root and affected package changelogs, Composer constraints,
  consumer fixtures, release-preview expectations, and generated artifacts for
  the synchronized 2.0.0 train.

## Validation and acceptance

- Add façade contracts for arrays, optional object dispatch, contextual calls,
  payload/document roots, records, NDJSON exhaustion/audit, and missing-adapter
  failures.
- Add extension-module contracts for providers, strategies, codecs,
  transformations, and object adapters in plain PHP, Symfony, and Laravel
  consumers.
- Preserve regression coverage for profile parsing, fragments, normalized and
  global rules, traversal/report paths, limits, embedded payloads, and legacy
  import.
- Require every removed suppression to have focused verification, retain
  documented justified suppressions, preserve 100% core line/branch coverage,
  and pass `PHP_VERSION=8.5 composer qa`, all consumer tests, release preview,
  and `graphify update .` after code changes.

## Decisions and assumptions

- All packages remain a synchronized 2.0.0 release train.
- 2.0 is a clean break; compatibility aliases are not retained.
- The single generic façade is intentional; the optional Serializer package
  remains the object implementation.
- The legacy importer remains in 2.x only as a deprecated migration aid and is
  removed in 3.0.
