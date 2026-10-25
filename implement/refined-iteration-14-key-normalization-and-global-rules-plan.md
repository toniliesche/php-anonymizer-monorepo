# Refined Iteration 14 Implementation Plan — Key Normalization and Global Rules

## Goal

Publish Iteration 14 as `1.5.0`, adding comparison-only key normalization and
profile-local global rules while preserving existing profile behavior and all
public anonymizer interfaces. The former Profile Governance and Controlled
Reuse work is postponed to Iteration 15.

## Profile contract

Profiles may add:

```yaml
key_normalization: true
global_rules:
  - field: email
```

- `key_normalization` defaults to `false`.
- `rules` remains a non-empty list for ordinary profiles, but may be omitted
  when `global_rules` is non-empty; at least one selector list must be present.
- `global_rules` is a list of scalar leaf rules, optionally with conditions.
  Direct scalar rules may use existing strategies, transformations, key IDs, and
  contextual behavior. `each: true` uses the existing scalar-list semantics.
- Global rules reject `children`, nested `each` rules, `embedded`, and
  `on_scalar`. They do not emit missing-field report entries.
- Existing profiles without these keys remain behaviorally compatible.

## Key matching and traversal

- Implement an internal ASCII identifier normalizer covering camelCase,
  PascalCase, snake_case, kebab-case, acronym, separator, and digit-boundary
  variants. Non-ASCII keys remain literal and are not Unicode case-folded.
- Normalization is comparison-only. Original dataset keys remain unchanged in
  transformed data, reports, audit paths, and encoded payloads.
- Resolve normalized collisions deterministically: an exact original spelling
  wins; otherwise the first declared canonical rule wins. Condition-path lookup
  uses exact input-key matching first, then the first canonical input key in
  map declaration order.
- Apply global rules at every map depth, including maps nested inside lists.
  Global rules are profile-local and do not cross embedded payload boundaries.
- A matching scoped rule takes precedence. If its condition does not match, a
  matching global rule remains eligible. Recursive traversal must preserve
  existing redact/preserve behavior, transformations, contextual strategies,
  embedded payload handling, and execution limits.
- Include normalization and global rules in the compiled profile fingerprint.

## Integration and documentation

- Keep `AnonymizerInterface`, document APIs, batch APIs, and NDJSON APIs
  unchanged; expose the feature through `ProfileDefinition` accessors and the
  existing traversal pipeline.
- Bundle configuration continues to validate and forward profile maps through
  the core parser; add integration coverage for valid and invalid definitions.
- Update `implement/anonymizer-v1-architecture-plan.md` and
  `implement/post-iteration-09-roadmap.md` so key normalization is Iteration 14
  and profile governance is Iteration 15.
- Update the profile schema, extension-author guide, package READMEs,
  changelogs, consumer examples, and release notes.

## Tests and acceptance

Add tests for:

- disabled-mode compatibility and every supported ASCII normalization variant;
- exact-spelling and declaration-order collision handling;
- normalized condition paths and unchanged output/report/audit key spelling;
- global-only profiles, nested maps, maps in lists, scalar leaves, and
  `each: true` lists;
- redact/preserve behavior, strategies, transformations, contextual execution,
  conditions, scoped precedence, and conditional fallback;
- invalid profile shapes, duplicate fields, forbidden global structures,
  execution limits, embedded-profile isolation, and safe diagnostics.

Run `PHP_VERSION=8.5 composer test`, `make fix`, `make refactor-fix`,
`PHP_VERSION=8.5 composer qa`, and `graphify update .`. Create annotated
`iteration-14` and `1.5.0` tags only after all checks pass.
