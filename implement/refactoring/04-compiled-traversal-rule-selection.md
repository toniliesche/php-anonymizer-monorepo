# Slice 4: Compiled Traversal Rule Selection

## Objective

Reduce per-field traversal work while clarifying the boundary between rule
selection and recursive mutation.

## Changes

- Compile immutable exact-field and normalized-field lookup maps for each
  immutable rule list during runtime construction. Preserve source order for
  missing-rule reporting and preserve first-match behavior for normalized
  collisions.
- Evaluate conditions only against the current map snapshot; do not cache a
  condition result across different maps.
- Make selection own scoped-versus-global precedence, normalized-key lookup,
  condition checks, missing-rule reporting, and unselected routing.
- Keep traversal responsible for recursion, execution budgets, pointer
  propagation, and value dispatching.
- Do not alter JSON-pointer construction or traversal context allocation in
  this slice. Revisit those only when the benchmark identifies them as a
  material cost.

## Tests and acceptance

- Characterize nested maps/lists, scalar policies, global rules, normalized
  keys, conditions, missing rules, embedded payloads, limits, and audit output.
- Add regression tests for the current mutation hotspots in traversal,
  conditions, reports, and payload processing.
- Benchmark wide maps and normalized-key workloads before and after the change;
  transformed data, reports, and failures remain identical.
