# Refined Iteration 31 — Mutation-Resistance Recovery

## Baseline and target

The current Infection run contains 2,331 covered mutants: 2,014 killed,
310 escaped, and 7 timed out (86.4% MSI). The existing 90% gate requires at
least 84 additional resolved mutants. The agreed final target is 95% MSI,
requiring at least 2,215 resolved mutants. Once that target is stable, raise
the enforced minimum in `tools/run-infection.php` from 90 to 95.

Preserve public APIs, anonymization behavior, error contracts, and the
existing seven-package Infection scope. Do not add a global Infection timeout,
source exclusions, broad suppressions, or mutation-specific production seams.

## Iteration 1 — Restore the 90% mutation gate

The first delivery targets 106 survivors: 67 in Bundle legacy
import/command/YAML handling and 39 in profile composition/cycle validation.
It must kill at least 84 additional mutants (2,098 total), remove all seven
timeout mutants, and add no Infection suppressions.

### Production changes

- Replace `LegacyRulePathConverter::build()` with a right-to-left `foreach`
  construction. Preserve terminal `[]` as `each: true` plus
  `on_scalar: redact`, wrap preceding fields as `children`, and keep all path
  validation before construction. A removed construction loop must fail
  immediately rather than recurse or reach an undefined index.
- Refactor `EmbeddedProfileReferenceGraph` DFS so traversal returns either
  `null` or the complete cycle path. Keep throwing the existing exception only
  in `assertAcyclic()`; removing that throw must terminate and fail the test,
  not recurse indefinitely.
- Add a reachable-fragment cycle preflight to
  `ProfileCompositionCompiler`, traversing top-level rules and nested
  `children`/`each` uses. Preserve malformed/unknown-use validation precedence,
  retain the current indexed diagnostic, and make the expansion-side guard
  finite if a mutated preflight throw is removed.
- Keep internal `Assert::*` type narrowing intact. Do not add seams or
  suppressions for equivalent method-removal mutants in this iteration; audit
  those contract cases in Iteration 3.

### Behavioral tests

- Legacy conversion tests must assert complete trees for leading/trailing-dot
  paths, multilevel and terminal `[]` paths, empty/dot-only/interior-empty
  paths, named definitions, fallback/value-type selection, macros, and
  unsupported object access.
- Converter tests must cover top-level versus nested rule precedence and Faker
  absent, disabled, enabled, malformed-locale, and seed variants, asserting the
  complete generated configuration.
- Command and infrastructure tests must assert JSON/YAML and uppercase
  extension handling, unsupported/missing/scalar/non-string-key documents,
  parse diagnostics, output replacement, check-mode statuses/messages, and
  file-write failures.
- Add JSON depth-boundary fixtures: a 511-level object must decode with the
  current depth of 512, while a 512-level object must fail. This distinguishes
  both `512 -> 511` and `512 -> 513` mutations.
- Add YAML encoding fixtures whose nesting distinguishes dump depths 3, 4, and
  5 for mapping and sequence documents.
- Profile-composition tests must assert full output for fragment uses inside
  both `children` and `each`, exact diagnostics for invalid names (including a
  trailing newline and trailing invalid character), malformed entries, unknown
  nested uses, and complete multi-fragment cycle paths.
- Profile-reference tests must assert direct and multi-profile embedded cycles,
  including the complete ordered cycle path and deterministic termination.

### Acceptance

- Focused legacy, profile-composition, and profile-reference PHPUnit suites
  pass before the full checks.
- `PHP_VERSION=8.5 composer test`, `test:coverage`, `mutation`, `lint`,
  `static`, and `refactor:check` pass.
- Infection reports at least 2,098 killed mutants / 90% MSI, zero timed-out
  mutants, unchanged seven-package scope, and no new
  `@infection-ignore-next-line` annotations.

## Iteration 2 — Strengthen core domain and runtime oracles

- Add strict condition, rule-option, profile-metadata, profile-definition, and
  rule-appender cases for missing keys, scalar type distinctions, normalized
  paths, malformed escapes, list/map boundaries, and invalid combinations.
- Expand traversal assertions for selected, missing, and unselected scalar/map/
  list values; `children`, `each`, string selection, pointer propagation,
  audit events, depth limits, and collection budgets.
- Exercise NDJSON laziness and deferred audits, event aggregation/order,
  ordinal formatting, blank/non-string/non-object records, invalid JSON, codec
  failures, and trimming boundaries.
- Build and use non-empty generators for every fluent-builder registration port
  and assert retained registrations and invalid collaborator failures.
- Assert explicit empty, merged, sorted, and rendered report/result behavior.
- Require no new timeouts and MSI of at least 93% (2,168 resolved mutants).

## Iteration 3 — Integrations and residual mutants

- Add boundary tests for transformations, embedded payload traversal, JSON/YAML
  codecs, pseudonymization date/HMAC behavior, Faker construction, persona
  locale normalization, and Laravel profile/YAML integration.
- Assert bundle compiler/registrar definitions, tags, arguments, optional
  integration behavior, and service wiring through container tests.
- Classify every remaining assertion/type-contract mutant. Test adversarial
  public extension collaborators where valid; otherwise retain the assertion
  and add one justified `@infection-ignore-next-line` with an explanation in
  `docs/quality-exclusions.md`.
- Never suppress a non-equivalent behavioral mutant or a timeout.
- Require at least 95% MSI (2,215 resolved mutants), then raise the Infection
  minimum to 95 and run the complete QA gate.

## Verification and hygiene

- After each batch, rerun Infection and triage the fresh `var/infection.log`;
  track killed, escaped, timed-out, and newly suppressed mutants by iteration.
- Run focused PHPUnit suites first, followed by `PHP_VERSION=8.5 composer test`,
  `test:coverage`, `mutation`, `lint`, `static`, and `refactor:check`.
- Run `PHP_VERSION=8.5 composer qa` after Iteration 3.
- Refresh Graphify after code changes; do not commit generated Graphify output.
- No changelog update is needed unless implementation changes a public package
  behavior.

## Acceptance criteria

- All seven package source roots remain in PHPUnit and Infection scope.
- Published line coverage remains 100% with no risky, skipped, incomplete,
  warning, deprecated, or otherwise invalid tests.
- No timeout mutants remain unresolved.
- The final enforced covered MSI is at least 95% with only individually
  justified, documented narrow suppressions.

## Iteration 1 implementation status (2026-08-24)

- Implemented bounded right-to-left legacy path construction and removed the
  recursive cycle timeout in embedded profile references and profile fragments.
- Added legacy converter precedence/Faker/array-path tests, JSON depth and
  uppercase-extension tests, YAML dump-depth tests, fragment composition and
  cycle-path tests, condition boundary tests, and NDJSON whitespace/order/error
  tests.
- Verification: 372 PHPUnit tests / 858 assertions, 100% published line
  coverage (1,767/1,767), lint, PHPStan, Rector, and Graphify refresh pass.
- Infection now has 2,062 killed, 271 escaped, and zero timed-out mutants
  (88.38% covered MSI). The 90% mutation gate is therefore not yet met; the
  remaining survivors are carried into the next iteration for targeted
  traversal, report/result, builder, command, and contract-mutant triage.

## Iteration 3 progress (2026-10-25)

- Current full Infection run: 2,223 killed, 116 escaped, zero timed out out of
  2,339 mutants (95% covered MSI). The acceptance threshold is met exactly.
- Added Unicode HMAC known-output and date-string boundary tests, corrected
  missing-versus-explicit-null condition matching, exercised malformed
  composition inputs, and added legacy reader/command and traversal budget
  assertions. Refactored cycle detection to fail finitely under removed-return
  mutants, eliminating both prior timeouts.
- Added end-to-end builder assertions that bulk strategy/transformation
  registration retains prior single registrations, plus a multi-global-rule
  order assertion and nested `children`/`each` strategy-reference validation.
- Added leading-whitespace JSON object handling, audit missing-profile rejection,
  failed atomic-write temp-file cleanup, and malformed nested legacy merger
  collection tests, direct-rule precedence after later fragment expansion,
  numeric bucket float validation, and legacy fallback/non-string boundaries.
  The full suite passes (414 tests, 1,024 assertions); published line coverage
  is 100% (1,771 lines). Infection passes the raised 95% minimum with 2,223
  killed, 116 escaped, and zero timeouts. PHP-CS-Fixer lint passes.
- `tools/run-infection.php` now enforces the 95% minimum. Graphify was refreshed
  after source changes. The enforced mutation gate passes at 95% with zero
  timeouts. Remaining escaped mutants are documented in the Infection report.
- Follow-up work resolved the PHPStan findings by extracting focused parsers,
  validators, and traversal helpers while retaining the finite-cycle guard.
  Rector reports no pending changes; PHPStan reports no errors and PHP-CS-Fixer
  lint is clean.
- The latest full coverage run passes with 415 tests / 1,027 assertions and
  100% published line coverage (1,784/1,784). The enforced Infection gate
  passes with 2,257 killed and 118 escaped mutants out of 2,375, zero timeouts
  (95.03% MSI). Infection-related guards and tests remain in place.
- Graphify has been refreshed after these implementation changes. The earlier
  concerns about PHPStan and Rector are resolved; the remaining escaped mutants
  are documented in the Infection report for future targeted work.
