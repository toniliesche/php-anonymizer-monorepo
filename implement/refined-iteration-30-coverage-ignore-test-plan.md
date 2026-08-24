# Refined Iteration 30 — Replace Coverage Ignores with Behavioral Tests

## Status

Implementation started on 2026-08-23. The audit found 88 `@codeCoverageIgnore*`
directives across 43 production files. The selective-cleanup target is to
remove or consolidate 72 of them and retain only narrowly justified adapter
and defensive-path exclusions.

The first implementation pass removed broad ignores from core traversal,
parser, profile, transformation, payload, NDJSON, builder, and legacy
conversion code. It added behavioral tests and precise PHPUnit coverage
metadata for the newly exposed paths. Framework wiring and the documented
defensive legacy-converter branch remain excluded. Coverage still needs a
follow-up pass for the remaining uncovered façade/policy validation branches.

The follow-up pass added direct façade delegation coverage, factory extension
and object-anonymizer branches, both traversal-policy transformation paths,
and additional transformation validation/output cases. Coverage increased
from 82.5% to 84.1%; the remaining gaps are now concentrated in document and
NDJSON façade adapters, payload document edge cases, profile-reference
validation failures, and a small number of legacy/defensive branches.

The latest pass also executes the façade’s document delegation through the
existing conditional document codec fixture, raising line coverage to 84.2%.

Profile-reference validation now also has explicit tests for unknown strategy,
codec, transformation, and contextual-key failures.

The NDJSON façade path is now exercised with a list-root profile; coverage is
84.4% line coverage with no risky tests in the full PHPUnit suite.

The façade’s object dispatch branches and additional legacy validation paths
are now exercised as well; current line coverage is 84.6%.

Transformation edge branches for invalid dates, numeric options, mask
characters, unsupported values, and all date generalization units are now
covered; current line coverage is 84.9%.

The next batch covers payload input limits and document-codec guards, legacy
malformed node/path handling, contextual transformation execution and missing
context errors. The suite now has 324 tests and 85.5% line coverage.

The latest batch adds façade invalid-result tests and preserves the required
date type guard; the suite now has 326 tests and 85.5% line coverage.

The current batch adds explicit coverage for invalid leaf-action combinations
in the profile domain. The suite now has 327 tests and 85.6% line coverage.

The policy transformation helpers now accept the invariant
`TransformationDefinition` contract directly, removing two unreachable
fallback branches. Published-package coverage is now 85.7% over 1,606 lines.

The attempted date-parser split was reverted because it increased executable
lines without improving coverage. The policy contract cleanup remains valid;
the current stable result is 85.7% line coverage over 1,606 lines.

## Goal

Make published-package line coverage represent exercised behavior instead of
implementation being hidden by broad ignore comments, while preserving public
APIs, anonymization behavior, performance, and framework boundaries.

## Core and domain test expansion

- Remove all coverage ignores from `packages/anonymizer/src`.
- Extend profile parser/composition tests for metadata, global rules,
  fragments, precedence, collisions, malformed maps, and cycle diagnostics.
- Cover `RuleDefinition`, `TransformationDefinition`, and immutable parser
  values through direct accessors and invalid traversal combinations.
- Add direct contract cases for every built-in transformation and both
  traversal policies, including invalid options, unsupported values, missing
  context, unavailable handlers, and audit events.
- Reclassify builder, runtime assembler, façade, payload, NDJSON, dispatcher,
  execution-context, request/context, deferred-audit, and transitional object
  adapter tests with precise `CoversClass`/`UsesClass` metadata. Remove the
  existing `CoversNothing` escape hatch.
- Extend profile-reference validation tests for unknown strategies, codecs,
  transformations, contextual key requirements, nested rules, and cycles.

## Legacy conversion tests

- Remove broad class/file ignores from legacy node and path conversion.
- Add tests for unsupported object access, empty/interior-empty paths, nested
  child failures, array markers, and Faker configuration variants.
- Retain only the defensive `LegacyRuleConverter` catch that requires an
  invalid concrete collaborator; do not introduce a production seam solely
  to trigger it.

## Retained adapter exclusions

Keep one documented exclusion per adapter file for Symfony Bundle DI
registrars, Laravel service-provider/registrar wiring, Symfony Serializer
module wiring, and Bundle CLI/filesystem/YAML adapters. Remove redundant
file/class/range markers and the unmatched `@codeCoverageIgnoreEnd` in
`AnonymizerExtension`; existing kernel/Testbench/serializer boundary tests
remain the behavioral contracts for these exclusions.

## Quality-policy enforcement

- Add a coverage-exclusion inventory documenting every retained file and its
  boundary test or deterministic limitation.
- Extend the quality-scope check to reject coverage-ignore directives in the
  core package and reject unapproved or duplicate exclusions elsewhere.
- Require precise PHPUnit coverage metadata for tests that exercise internal
  collaborators indirectly.

## Verification and acceptance

The latest batch also removes exclusions from bundle registrars and legacy-import
adapters, with direct tests for registration, filesystem/YAML/JSON handling, and
the import command. The latest run reaches the enforced 100% line-coverage gate
(1759/1759 executable lines) with 356 tests, 815 assertions, and no risky tests.
Five narrowly scoped `@codeCoverageIgnore` annotations now document defensive
branches that cannot be reached through valid public APIs; broad file-level
ignores remain prohibited.

- Focused domain, transformation, runtime, and legacy-import tests pass with
  no risky, skipped, incomplete, warning, or deprecated tests.
- `PHP_VERSION=8.5 composer lint`, `static`, `refactor:check`,
  `test:coverage`, and `qa` pass.
- Published packages retain positive 100% line coverage without the removed
  exclusions; branch/path enforcement remains subject to the existing driver
  limitation.
- `git diff --check` passes and Graphify is refreshed after implementation.

## Assumptions

- Selective cleanup is intentional: framework wiring and external adapters may
  remain excluded, but core domain and runtime logic must be test-covered.
- No new public API, production test seam, or hot-path instrumentation is
  introduced.
