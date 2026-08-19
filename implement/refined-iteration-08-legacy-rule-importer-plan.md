# Refined Iteration 8 — Legacy Rule Importer

## Summary

Add a one-way Symfony Bundle command:

```text
anonymizer:import-legacy-rules <input> <output> [--check]
```

It accepts legacy YAML (`.yaml`/`.yml`) and JSON (`.json`) rule documents and
generates a canonical new Bundle configuration document rooted at
`anonymizer:`. No legacy parser or compatibility API is added to the runtime.

This document refines [Iteration 8](iterations/08-legacy-rule-importer.md); the
initial iteration plan remains the concise roadmap entry.

## Implementation

- Add `symfony/console:^8.1` as a direct Bundle dependency and register the
  command as a Bundle service tagged `console.command`.
- Keep conversion framework-independent in `Application\\LegacyImport`; place
  YAML/JSON decoding, canonical YAML rendering, and atomic file writing in
  `Infrastructure\\LegacyImport`. The command is the outer Symfony adapter.
- Accept either legacy root shape:
  - `rules: {...}` from legacy rule loaders.
  - `anonymizer: { rules: {...} }` from legacy Symfony configuration.
  - Reject ambiguous roots or malformed documents with profile/node locations.

- Convert each legacy profile into `mode: redact` and v1 `rules`:
  - Legacy string paths become nested `field` rules.
  - `[]` markers become `each: [...]` for branches and `each: true` for leaves.
  - Legacy array nodes map `name` to `field`, `children` to `children`, and
    `is_array` to the corresponding `each` form.
  - `value_type` and `[#value]` annotations map only the fifteen legacy Faker
    values to their stable `faker.*` strategy IDs.
  - `filter_field`/`filter_value` and `[%field/value]` map to
    `when: {path, equals}`.
  - `nested_type`/`nested_rule` and `[?codec/profile]` map only `json` and
    `yaml` to `embedded`.
  - Scalar fallbacks map to `on_scalar: preserve` or `redact`; an anonymizing
    fallback with `value_type` also receives the mapped Faker strategy.
  - Merge equivalent shared path prefixes; reject conflicting duplicates.

- Treat as conversion errors: malformed legacy syntax, mixed string/map node
  styles within one profile, unsupported Faker types/codecs, non-array data
  access (`autodetect`, `property`, `reflection`, `setter`), custom legacy
  providers, unrepresentable settings, invalid emitted profiles, missing
  embedded profiles, and embedded-profile cycles.
- Do not silently drop obsolete object-access annotations. Diagnostics identify
  the profile, legacy node/path, unsupported construct, and required manual
  migration action.
- Ignore standard parser settings because the importer reads both supported
  syntax forms directly. Emit a non-fatal reminder when legacy Serializer
  settings require the application to configure its own Symfony Serializer.
- Emit `anonymizer.faker.enabled: true` and preserve `data.faker_seed` when
  legacy Faker is enabled or converted strategies require it; warn that
  `php-anonymizer/anonymizer-faker` must be installed.

## Command behaviour and documentation

- Always generate deterministic YAML with a final newline and stable schema
  ordering.
- Normal mode writes only after successful parsing, conversion, and v1
  validation; write through a sibling temporary file then atomically replace
  the target.
- `--check` performs the same conversion without writing:
  - exit `0` when the existing output exactly matches canonical generated YAML;
  - exit `1` when the target is absent or outdated;
  - exit `2` for invalid input or conversion diagnostics.
- On any conversion error, leave an existing output untouched and create no new
  output.
- Add a migration guide with old-to-new examples, required Faker installation,
  object-access migration through the existing Serializer adapter, unsupported
  constructs, command usage, and CI `--check` usage.
- Add the Iteration 8 changelog entry when implementation begins.

## Tests and verification

- Unit fixtures cover YAML and JSON input; both legacy rule syntaxes; nested
  paths; lists; all supported Faker mappings; filters; JSON/YAML embedded
  profiles; scalar fallbacks; deterministic rendering; profile-reference
  validation; and all unsupported constructs.
- Command tests cover registration, successful atomic generation, canonical
  `--check`, stale/missing output, malformed input, diagnostics, and
  preservation of an existing output after failure.
- Add Bundle integration coverage using `CommandTester`; retain strict PHPUnit
  coverage metadata.
- Run Composer normalization and metadata validation, PHP-CS-Fixer, PHPStan,
  Deptrac, dependency analysis, Rector checks, PHPUnit/coverage, Infection,
  Bundle consumer installation, and release-preview/artifact checks.

## Assumptions

- The generated document is complete Bundle configuration, not a profile
  fragment.
- Importing legacy allow-list behaviour, arbitrary custom generators/accessors,
  XML payloads, and runtime regexp parsing is out of scope.
- PHP 8.5 and Symfony 8 remain the supported platform baseline.
