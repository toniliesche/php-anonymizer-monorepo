# Iteration 06 — Faker, YAML, and European Personas

## Goal

Provide optional generated-value, fixed-persona, and YAML integrations while
keeping the core framework-independent.

## Deliverables

- `php-anonymizer/anonymizer-faker` with the documented legacy formatter
  equivalents, configurable locale, and deterministic seed support.
- YAML payload codec in the Bundle using `symfony/yaml`; never require
  `ext-yaml`.
- Independent Composer installation and configuration paths for all optional
  integrations.
- Optional `php-anonymizer/anonymizer-personas` adapter with fixed European
  personas for `en`, `de`, `es`, `fr`, `it`, `nl`, and `pt`: Joe Bloggs, Max
  Mustermann, Juan García, Jean Dupont, Mario Rossi, Jan Jansen, and António
  Silva, plus specific regional personas for `en_IE`, `de_AT`, `de_CH`, and
  `nl_BE`.
- Faker is enabled only through Bundle configuration and uses the stable
  `faker.*` snake-case IDs with `en_US` and deterministic integer/text seeds.
- YAML is always registered by the Bundle under the `yaml` codec ID and accepts
  mapping roots only.
- Personas are enabled through `anonymizer.personas.enabled` with a language
  or matching European regional locale such as `de` or `pt_PT`; conflicting
  regions such as `en_US` and `pt_BR` are rejected.
- The Bundle reserves all `persona.*` IDs against custom extension collisions.

## Acceptance tests

- Each supported Faker strategy produces a valid value and repeats deterministically
  for a fixed seed.
- JSON and YAML implementations pass the shared payload-codec contract suite.
- A Symfony configuration enables Faker and YAML without making either a core
  dependency.
- A Symfony configuration enables Personas without making the Personas package
  a core dependency.

## Handoff

Document all supported formatter IDs, seed semantics, locale defaults, and the
fixed European persona corpus.
