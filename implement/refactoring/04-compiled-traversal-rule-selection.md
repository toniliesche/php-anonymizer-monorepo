# Iteration 26 — Compiled Traversal Rule Selection

## Objective

Move immutable rule lookup work from every traversed map to runtime
construction. Keep recursive mutation, budgeting, pointer propagation, and
value dispatch in traversal while giving selection one explicit owner.

No public PHP API or profile configuration shape changes. Global rule
conditions honor the documented `when` contract: a false local condition may
fall through to an eligible global rule, and a false global condition leaves
the value unselected.

## Implementation specification

- Compile every profile's scoped rule list, including nested `children` and
  list-form `each` rules, when `ArrayTraversal` is constructed.
  - Retain source-ordered rules for missing-path reporting.
  - Build immutable exact-field maps for constant-time lookup.
  - Build first-declaration-wins normalized maps only for profiles with
    `key_normalization: true`; profiles without normalization do not pay the
    normalized-index memory or startup cost.
  - Reuse one immutable empty compiled list for unselected recursive maps.
- Add internal compiled-profile, compiled-rule-list, and rule-selector
  collaborators. The selector receives only the current map snapshot and the
  compiled scoped/global lists.
  - Resolve scoped rules before global rules.
  - Prefer exact spelling, then the first normalized collision match.
  - Evaluate each rule/map condition at most once and never reuse a result for
    another map snapshot.
  - Own scoped/global precedence, condition checks, normalized lookup, and
    source-ordered missing-rule reporting.
- Pass compiled nested lists through `TraversalExecutionContext` and selected
  value dispatch. Keep traversal responsible for recursion, execution budgets,
  JSON Pointers, embedded-payload boundaries, and policy dispatch.
- Remove the obsolete linear `RuleIndex` implementation. Keep all new
  declarations internal; do not expand the public API manifest.
- Add a fixed local benchmark harness for wide-map and normalized-key
  scenarios. Report median elapsed time, peak memory, and an output digest
  before/after. Keep it informational in this iteration; Slice 6 will extend
  it into the isolated CI benchmark suite.

## Compatibility and performance invariants

- Preserve transformed data, output key spelling, report path ordering,
  missing-path ordering, audit events, exception types/messages, limits,
  embedded profile boundaries, and lazy batch/NDJSON behavior.
- Preserve exact-key missing-path semantics even when a normalized equivalent
  input key exists.
- Keep compiled indexes proportional to the number of declared rule fields;
  do not introduce per-operation copies or cross-map condition caches.
- Compare benchmark runs with the same PHP version and runtime settings. A
  timing result is evidence for investigation, not a CI threshold here;
  deterministic correctness and resource-bound tests remain blocking.

## Tests and acceptance

- Unit-test compiled lookup maps for exact lookup, normalized lookup,
  exact-over-normalized precedence, first normalized collision, nested
  children, list rules, and empty-list reuse.
- Unit-test selection for local/global precedence, false-local fallback,
  conditional globals, snapshot isolation, condition memoization within one
  map, and source-ordered missing reporting.
- Extend traversal contracts for nested maps/lists, normalized keys,
  embedded payloads, limits, audits, payload/document operations, and
  no-report output equivalence.
- Run focused core tests, then `PHP_VERSION=8.5 composer qa`; refresh the
  knowledge graph with `graphify update .` after implementation. Record the
  benchmark comparison with the iteration results.

## Documentation and release hygiene

- Update the root and core-package `CHANGELOG.md` files for the internal
  selection compilation and documented global-condition correction.
- Keep this slice document as the implementation handoff; update the
  refactoring index only when the slice is implemented and accepted.
