# Refined Iteration 3 — Profile Semantics

This document refines the initial iteration brief at
`iterations/03-profile-semantics.md` into an implementation-ready increment.
The original roadmap remains unchanged.

## Goal

Add inverse preserve-mode traversal, declarative conditions, and explicit
scalar fallbacks while preserving the array-only hexagonal core boundary.
Symfony integration, codecs, replacement strategies, YAML parsing, and object
support remain deferred to later iterations.

## Public profile schema and API

- Add a public `ProfileMode` backed enum with `redact` and `preserve`; expose
  it through `ProfileDefinition::mode()`.
- Extend rules with optional `when` and `on_scalar` keys. Valid rule keys are
  `field`, `children`, `each`, `when`, and `on_scalar`.
- `on_scalar` is allowed only on `children` or `each` rules and accepts the
  explicit action `redact` or `preserve`. The action overrides the enclosing
  profile mode when a normally-container field contains a string. Without it,
  the existing shape error remains in force.
- `when` has exactly `path` and one operator:

  ```yaml
  when: { path: 'type', equals: 'person' }
  when: { path: 'metadata/type', in: ['person', 'customer'] }
  ```

- Condition paths are relative to the containing map, use slash-separated
  segments, have no leading slash or parent traversal, and use RFC 6901
  `~0`/`~1` escaping. Intermediate values must be maps; missing paths and
  non-map intermediates evaluate as a non-match.
- `equals` accepts one scalar or `null`; `in` accepts a non-empty list of
  scalar-or-null values. Comparisons are strict.
- `strategy` and `embedded` remain reserved schema vocabulary but are rejected
  until Iteration 4 introduces their extension ports.

## Runtime and architecture

- Keep `ArrayAnonymizer` as the application entry point. Extract focused
  application-layer traversal helpers when that keeps redaction, preserve
  traversal, condition evaluation, and report assembly readable. Domain
  objects remain immutable compiled profile metadata.
- Redact mode retains all Iteration 2 behavior, with `when` gating rules and
  `on_scalar` providing the explicit scalar alternative.
- Preserve mode treats rules as a recursive allow-list: selected strings stay
  unchanged, while every other string anywhere in the supplied array tree is
  replaced with `[REDACTED]`. Unselected non-string values remain unchanged.
- A selected leaf with a non-string value, a selected container with an
  incompatible shape, or an unsupported selected scalar value throws the
  existing path-aware `UnsupportedValue` exception.
- Evaluate each condition against an immutable snapshot of its containing map
  before transformations at that level. Conditions therefore do not change
  meaning because an earlier rule redacted a sibling.
- A non-matching condition skips the rule entirely: it adds neither a missing
  nor an anonymized report entry. In preserve mode, its value is not
  allow-listed and is therefore redacted if it is a string.
- Continue using RFC 6901 JSON Pointer report paths. Preserve mode records
  every redacted unallow-listed string in `anonymizedPaths()`.
- Keep report ordering deterministic: configured rule order for selected and
  missing paths, and depth-first input order for preserve-mode automatic
  redactions.
- Preserve input immutability and all existing Iteration 1–2 redaction
  behavior.

## Validation, tests, and documentation

- Validate profile mode, all new rule keys, fallback placement/actions,
  condition shape, path escaping, exactly one condition operator, and operand
  types. Errors must identify precise rule-tree locations.
- Test preserve mode across maps, lists, nested configured containers,
  `each: true`, and lists of maps. Verify that unselected strings are redacted
  while numbers, booleans, nulls, and unselected containers remain intact.
- Test `equals` and `in`, nested and escaped condition paths, strict type
  comparisons, missing/non-map condition paths, condition snapshot semantics,
  false-condition reporting, fallback overrides, wrong selected shapes, input
  immutability, and deterministic report ordering.
- Update the core README with profile-mode, condition, and `on_scalar`
  examples. Add a concise profile-schema reference and update `CHANGELOG.md`.
- Run `PHP_VERSION=8.5 composer qa`, including all existing quality gates and
  consumer-install verification.
- After the user creates the implementation commit, create the annotated
  `iteration-3` milestone tag. Do not create a semantic-version tag.

## Assumptions

- The replacement remains `[REDACTED]`; custom replacement strategies begin in
  Iteration 4.
- Preserve mode intentionally redacts strings outside the configured rule
  tree, matching its allow-list meaning.
- Conditions inspect only sibling data in the current map and cannot reach
  parent or root scopes.

