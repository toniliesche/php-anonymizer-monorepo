# Iteration 14 — Key Normalization and Global Rules

## Goal

Allow profiles to match equivalent key spellings and optionally anonymize named
keys at any depth, while preserving the dataset’s original structure and key
spelling.

## Deliverables

- Optional `key_normalization: true` profile behavior that canonicalizes word
  tokens for matching across camelCase, PascalCase, snake_case, kebab-case,
  acronym, separator, and digit-boundary variants. Normalization also applies
  to condition-path lookup when enabled.
- Comparison-only matching: never rename dataset keys in output, reports,
  audits, JSON Pointers, or encoded payloads.
- Deterministic normalized-rule collision handling: prefer the rule whose
  declared field exactly matches the original dataset key; otherwise use the
  first canonically matching rule in declaration order.
- Optional `global_rules` list of normal leaf rule objects. Permit global
  scalar matches at every map depth, including maps in lists, and support
  `each: true` for lists of scalar values. Global rules use existing strategies,
  transformations, contextual behavior, and preserve/redact semantics.
- Global rules are not structural rules: reject `children`, nested `each`
  rules, `embedded`, and `on_scalar`. A matching scoped rule takes precedence;
  a non-matching conditional scoped rule leaves the global rule eligible.
  Allow profiles containing global rules without scoped rules, provided at
  least one selector is defined.

## Acceptance tests

- Cover disabled-mode compatibility; all supported normalization variants;
  normalized conditions; exact-spelling collision precedence; declaration
  order fallback; duplicate/invalid profile definitions; and unchanged output
  and report key spelling.
- Cover global-only profiles, scalar matches, `each: true` scalar lists,
  nested maps, maps inside lists, preserve/redact behavior, strategies,
  transformations, contextual execution, conditional fallback, scoped-rule
  precedence, and execution limits.
- Cover rejected structural global rules, invalid list item shapes, report/audit
  pointers, embedded profile boundaries, and non-disclosure of source values.

## Handoff

Update profile schema, extension guidance, package documentation, changelogs,
consumer examples, and compatibility tests before publishing the next minor
release. Refresh Graphify output after implementation; do not commit generated
analysis caches.
