# Refined Iteration 21 — Verified boundaries and extension guidance

## Summary

Complete the 2.0 public-boundary cleanup: review published-source coverage,
PHPStan, and Infection suppressions and remove only those whose replacement
with meaningful tests or safer typing improves the project; internalize every
non-indexed implementation symbol; and replace the extension guide with
contract-complete documentation.

This iteration also closes two previously accepted 2.0 removals that remain
in source: delete `AnonymizerFactory`, `SymfonyAnonymizer`, and
`SymfonyObjectAnonymizer`; move the required serializer behavior directly
into `SymfonyObjectAnonymizerAdapter`.

## Implementation changes

### Framework extension discovery

- Add the targeted framework extension-discovery API:
  - Symfony and Laravel accept `anonymizer.extension` services implementing
    `AnonymizerExtensionInterface`.
  - Apply modules after existing provider, strategy, codec, and
    transformation registrations; collisions retain builder failure
    semantics.
  - Symfony consumes modules by descending tag `priority` (default `0`);
    Laravel uses tag-registration order.
  - Extend only the internal `AnonymizerBuilderFactory` wiring signature as
    needed; do not add another core extension interface or compatibility shim.

### Clean-break removals

- Remove `AnonymizerFactory` and migrate its tests and callers to
  `AnonymizerBuilder`.
- Remove `SymfonyAnonymizer` and `SymfonyObjectAnonymizer`; move their tested
  normalization, denormalization, context, and result behavior into
  `SymfonyObjectAnonymizerAdapter`.
- Update framework registrations, consumer fixtures, API documentation, and
  migration references to describe these as removed v1 entry points.

### Selective suppression, coverage, and mutation cleanup

- Review every `@codeCoverageIgnore*`, production `@phpstan-ignore*`, and
  `@infection-ignore*` annotation in `packages/*/src` individually.
- Remove suppressions for externally observable validation, parsing,
  extension registration, serializer behavior, and legacy diagnostics when
  focused tests provide meaningful protection.
- Resolve PHPStan suppressions through narrower values, guards, and helper
  methods where that improves type safety; retain justified vendor-contract
  and guarded-conversion suppressions.
- Retain suppressions for framework/container wiring, immutable plumbing,
  accessor-only values, optional-package absence checks, impossible defensive
  paths, and mutation cases whose alternatives are behaviorally equivalent.
- Add direct branch, failure-path, and mutation-killing tests only for the
  reviewed cases where they improve confidence; do not require zero
  production suppressions or a blanket mutation-score increase.
- Preserve the test-only Symfony deprecation suppression, since it is not in
  published code.

### Internal boundary

- Treat `docs/api-surface-v2.md` as the exact public manifest.
- Add `@internal` to production implementation symbols not listed there as an
  IDE/documentation hint only; do not use the annotation as an architecture
  enforcement mechanism.
- Keep the façade, builder, extension ports and contexts, profile/rule/result
  values, documented exceptions, optional-package factories/ports, Serializer
  extension/results/exceptions, Bundle bootstrap/configuration/command, and
  Laravel provider public.
- Keep Deptrac’s existing package/namespace-layer configuration simple; do not
  split collectors by symbol visibility, derive collectors from the API
  manifest, or use `@internal` for enforcement. Add only the minimal explicit
  package rule needed to prevent optional integrations from depending on the
  core implementation layer.
- Add a small Composer check that uses the API manifest to reject non-public
  FQCNs from public PHP examples in root/package READMEs, `docs/`, and
  consumer fixtures. Run it as part of `composer check`.

### Extension guide and release documentation

- Rewrite `docs/extension-author-guide.md` around the builder/module model
  with executable examples for profile providers, replacement and contextual
  strategies, map/document codecs, typed/contextual transformations, object
  adapters, and multi-port modules.
- Document stable IDs, eager validation, duplicate/built-in collision rules,
  context privacy, error contracts, framework registration including
  `anonymizer.extension`, compatibility promises, and a PHPUnit contract-test
  template.
- Correct stale README claims about removed capability bindings.
- Update the root changelog plus core, Serializer, Bundle, and Laravel package
  changelogs for the removal and new framework module tag.
- Leave the extensive User Guide and broad migration/release closure to
  Iteration 23.

## Tests and acceptance criteria

- Verify every suppression removed in this iteration with PHPUnit coverage or
  mutation tests, and record the reason for each retained suppression.
- Retain 100% configured published line and branch coverage; do not make
  removal of justified production suppressions an acceptance criterion.
- Cover module ordering, successful Symfony/Laravel module registration,
  duplicate failures, invalid module services, and existing individual-tag
  compatibility.
- Verify factory and legacy serializer symbols are absent and their
  builder/adapter replacements preserve arrays, objects, reports, contexts,
  payloads, records, NDJSON, and audit behavior.
- Verify the minimal Deptrac package rule and the manifest-based documentation
  checker reject their respective invalid references independently of
  `@internal` annotations.
- Run focused suites, then:

  ```bash
  PHP_VERSION=8.5 composer test
  PHP_VERSION=8.5 composer test:coverage
  PHP_VERSION=8.5 composer static
  PHP_VERSION=8.5 composer lint
  PHP_VERSION=8.5 composer refactor:check
  PHP_VERSION=8.5 composer check
  PHP_VERSION=8.5 composer qa
  ```

- Run relevant consumers, release preview, `git diff --check`, and
  `graphify update .`; generated Graphify output remains uncommitted.

## Fixed decisions and assumptions

- Targeted public additions are allowed only for the framework
  `anonymizer.extension` discovery tag; existing core extension interfaces
  remain frozen.
- This remains a clean 2.0 break: no aliases, deprecated factory shims, or
  retained direct serializer dispatchers.
- Framework wiring may use core internals; applications, optional extension
  packages, consumer fixtures, and public examples may not.
- Suppression removal is evidence-based and limited to cases where stronger
  tests or resolved analysis errors provide concrete maintenance value.
