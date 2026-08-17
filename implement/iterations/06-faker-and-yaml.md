# Iteration 06 — Faker and YAML Integrations

## Goal

Provide optional replacements for the historical generated-value and YAML
payload features.

## Deliverables

- `php-anonymizer/anonymizer-faker` with the documented legacy formatter
  equivalents, configurable locale, and deterministic seed support.
- YAML payload codec in the Bundle using `symfony/yaml`; never require
  `ext-yaml`.
- Independent Composer installation and configuration paths for both optional
  integrations.

## Acceptance tests

- Each supported Faker strategy produces a valid value and repeats deterministically
  for a fixed seed.
- JSON and YAML implementations pass the shared payload-codec contract suite.
- A Symfony configuration enables Faker and YAML without making either a core
  dependency.

## Handoff

Document all supported formatter IDs, seed semantics, and locale defaults.
