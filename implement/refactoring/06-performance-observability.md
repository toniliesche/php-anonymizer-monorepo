# Slice 6: Performance Observability and Regression Protection

## Status

Benchmarking and deterministic laziness protections are implemented. Baseline
calibration and warning-only comparison remain scheduled follow-up work.

## Objective

Prevent material application runtime and memory regressions while keeping
performance measurement reproducible and resistant to shared-runner noise.

## Benchmark command

- Add `composer benchmark` backed by a PHP harness that runs each scenario in
  isolated child processes, performs warmups, collects multiple samples, and
  emits median wall time and peak memory as JSON.
- Benchmark a balanced production suite: narrow and wide maps, normalized-key
  and conditional matching, nested rules, output-only versus report/audit
  operation, lazy NDJSON, and large builder/profile registration.
- Store scenario definitions and expected semantic output in repository-owned
  fixtures so benchmark changes cannot silently change the work being measured.

Implemented by `tools/benchmark.php`: each sample runs in a fresh PHP child
process, with configurable `BENCHMARK_WARMUPS`, `BENCHMARK_SAMPLES`, and
`BENCHMARK_ITERATIONS`. Results include median wall time, peak memory, and a
semantic output digest for wide, normalized, nested, and report scenarios.

## Deterministic PR protection

- Add tests proving that batch and NDJSON sources are not eagerly consumed,
  output-only operations do not retain reports, audit state grows with unique
  event shapes rather than record count, and execution limits remain bounded.
- Keep these invariants in the normal test suite; they are blocking regardless
  of timing variability.

Batch and NDJSON tests explicitly verify that source generators are not pulled
until the returned iterable is started.

## CI trend monitoring

- Add a scheduled and manually dispatched GitHub Actions benchmark workflow.
  Publish the JSON result as an artifact and compare it to a calibrated,
  checked-in baseline.
- During calibration, collect repeatable results on the same runner. Afterwards
  emit warnings for median-time regressions above 10% or peak-memory
  regressions above 15%; keep this workflow non-blocking until a stable runner
  baseline justifies stricter enforcement.

The scheduled/manual artifact workflow is in `.github/workflows/benchmark.yml`;
baseline comparison is intentionally deferred until runner calibration.

## Optimization policy

- Use the compiled rule-selection work in Slice 4 as the first measured
  optimization target.
- Only optimize pointer/context allocation, codec handling, or builder copying
  after a benchmark attributes a material cost to it. Preserve laziness,
  pointer/error semantics, and builder immutability.
