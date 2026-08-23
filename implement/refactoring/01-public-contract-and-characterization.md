# Slice 1: Executable Public Contract and Characterization Coverage

## Objective

Freeze the public API before structural refactoring. The executable manifest
becomes the canonical semver contract; `docs/api-surface-v2.md` remains the
human-readable index. The additive, deprecate-then-evolve compatibility policy
supersedes older planning text that described a clean break.

## Public contract model

- Add `tools/public-api-manifest.php`, keyed by Composer package and FQCN.
- For every supported or transitional public type, record its status, kind,
  inheritance/interfaces, modifiers, enum cases, public constants/properties,
  and contracted constructors/methods.
- Method records include staticness, parameter names/types, reference and
  variadic flags, defaults, return types, and precise public PHPDoc generic
  types (`@param`/`@return`).
- Require the API document to contain a complete FQCN index with package,
  status, and replacement information for deprecated entries.
- Every production declaration not in the manifest must be marked `@internal`.
  A public member of a public framework type may be excluded only when that
  member itself is explicitly marked `@internal`.

The supported surface includes the façade and builder, extension ports and
contexts, profile/rule schema values, result and audit values, documented
exceptions, framework entry points, and optional-package factories. Transitional
compatibility types remain manifest-backed until their next-major removal.

## Contract leak remediation

Preserve current behavior while preventing new dependencies on implementation
details:

- Keep `JsonPointer` temporarily manifest-backed and deprecated because the
  public replacement/transformation contexts expose it. Add `pathString()` to
  both contexts, deprecate `path()`, and document the string path as the
  replacement.
- Keep `NdjsonAuditProviderInterface` temporarily manifest-backed and
  deprecated because the public NDJSON result constructor accepts it. Add a
  provider-free construction path using an audit-supplying closure.
- Mark `AnonymizationReport` internal; it is an accumulator, not a returned
  public result.
- Add Faker and Personas factory methods returning
  `ReplacementStrategyInterface`; deprecate concrete-returning `createStrategy()`
  methods. Update factory PHPDoc to expose the port type and retain
  `PersonaReplacementStrategyFactory::IDS` as a supported constant.
- Mark engines, registries, traversals, policies, compilers, built-ins,
  adapters, profile composers, and legacy-import collaborators internal.
  Do not internalize documented or transitional compatibility types in this
  slice.

## Enforcement and documentation

Extend `tools/check-public-api.php` to:

- reflect every manifest type and fail for missing, internalized, or changed
  members;
- normalize reflected types to fully qualified names and compare defaults,
  PHPDoc generics, and declared members deterministically;
- tokenize every production declaration and fail if it is neither
  manifest-backed nor `@internal`;
- fail when a manifest type or member is itself marked `@internal`;
- retain the public-example scan, deriving forbidden project symbols from the
  manifest rather than a hand-maintained list;
- verify that every manifest FQCN appears in `docs/api-surface-v2.md`.

Add an authority note to the refactoring roadmap and API documentation stating
that replacements are introduced before deprecated APIs are removed in a
future major release. Add `Unreleased` changelog entries only for core, Faker,
and Personas, which receive public replacement methods or deprecations.

## Characterization tests

- Exercise the façade through `AnonymizerBuilder` for arrays, payloads,
  documents, records, NDJSON, reports, audit output, contexts, object requests,
  results, and documented exception categories.
- Lock exact output, path/event ordering, audit aggregation, missing-path
  behavior, profile selection, and result types.
- Use side-effecting generators to prove records and NDJSON remain lazy; audit
  becomes final only after complete stream consumption.
- Verify extension-port compatibility with test providers, strategies,
  transformations, codecs, and object adapters.
- Add shared framework contract scenarios for public bindings, profiles,
  limits, optional extensions, extension order, and invalid configuration;
  execute them through Symfony and Laravel bootstraps.
- Add regression cases for string paths, provider-free NDJSON construction,
  and factory methods returning extension ports.

## Acceptance

- Every production declaration is exactly one of supported, deprecated
  transitional, or `@internal`.
- Every supported/deprecated type and contracted member matches the manifest
  and appears in the API index.
- Public examples reference only manifest-backed project symbols.
- No caller-visible behavior changes occur; deprecated APIs remain functional.
- `PHP_VERSION=8.5 composer check`, coverage, mutation, consumer suites, and
  `PHP_VERSION=8.5 composer qa` pass; refresh Graphify after code changes.
