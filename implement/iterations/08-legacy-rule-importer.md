# Iteration 08 — Legacy Rule Importer

## Goal

Offer a safe one-way migration from the old library without retaining runtime
compatibility code.

## Deliverables

- Bundle command: `anonymizer:import-legacy-rules <input> <output>` plus
  `--check` for CI.
- Conversion of supported old paths, list markers, Faker annotations, filters,
  embedded JSON/YAML rules, and scalar fallbacks to the new YAML schema.
- Explicit diagnostics for obsolete data-access annotations and any
  non-convertible construct. Failed conversions create no output file.

## Acceptance tests

- Fixture coverage for every supported legacy construct and generated YAML.
- Unsupported constructs produce actionable diagnostics.
- `--check` has appropriate success/failure exit codes and failed imports leave
  no partial file behind.

## Handoff

Publish a migration guide mapping legacy concepts to the v1 schema. Do not add a
runtime legacy parser or compatibility API.
