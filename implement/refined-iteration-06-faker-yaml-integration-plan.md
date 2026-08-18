# Refined Iteration 6 — Faker, YAML, and European Personas

## Summary

Add the optional Faker and fixed-European-Personas adapter packages plus a
Bundle-provided YAML payload codec. The framework-free core remains unchanged;
Symfony configuration wires the optional integrations into the existing
extension ports.

## Public API and configuration

- Add `PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement`:
  - `FakerFormatter` enum defining the supported formatter mapping.
  - `FakerReplacementStrategy`, implementing the core
    `ReplacementStrategyInterface`.
  - `FakerReplacementStrategyFactory`, creating the complete strategy set for
    a locale and optional seed.
- Publish lowercase, snake-case strategy IDs only. Do not retain legacy
  aliases:

  | Strategy ID | Faker formatter | Legacy equivalent |
  | --- | --- | --- |
  | `faker.building_number` | `buildingNumber` | `buildingNumber` |
  | `faker.city` | `city` | `city` |
  | `faker.company` | `company` | `company` |
  | `faker.country` | `country` | `country` |
  | `faker.email` | `safeEmail` | `email` |
  | `faker.first_name` | `firstName` | `firstName` |
  | `faker.house_number` | `buildingNumber` | `houseNumber` |
  | `faker.last_name` | `lastName` | `lastName` |
  | `faker.name` | `name` | `name` |
  | `faker.password` | `password` | `password` |
  | `faker.postcode` | `postcode` | `postcode` |
  | `faker.street` | `streetName` | `street` |
  | `faker.street_number` | `buildingNumber` | `streetNumber` |
  | `faker.username` | `userName` | `username` |
  | `faker.zip` | `postcode` | `zip` |

- Add optional Symfony configuration:

  ```yaml
  anonymizer:
    faker:
      enabled: true
      locale: en_US
      seed: 1234
  ```

  `enabled` defaults to `false`. Enabling it requires
  `php-anonymizer/anonymizer-faker`; otherwise container creation fails with an
  actionable installation message. `locale` defaults to `en_US`. `seed`
  accepts an integer or string; integer seeds pass through, while textual seeds
  are deterministically normalized to an unsigned 32-bit integer before being
  passed to Faker.

- Add a Bundle-owned `YamlPayloadCodec` with ID `yaml`, backed by
  `symfony/yaml`. It safely parses only mapping roots, rejects invalid or
  unsupported YAML types, and emits YAML from maps. It is always available
  once the Bundle is installed; `ext-yaml` is never required.
- Reserve `yaml` and all documented `faker.*` IDs in the Bundle compiler pass
  so custom tagged services cannot silently replace built-ins.

### Fixed European personas

- Add optional `php-anonymizer/anonymizer-personas`, a framework-free package
  with stable `persona.*` strategies for name, contact, and address fields.
- Support British (`en`/`en_GB`), German (`de`/`de_DE`), Spanish
  (`es`/`es_ES`), French (`fr`/`fr_FR`), Italian (`it`/`it_IT`), Dutch
  (`nl`/`nl_NL`), and Portuguese (`pt`/`pt_PT`) personas, plus specific
  regional personas for Ireland (`en_IE`), Austria (`de_AT`), Switzerland
  (`de_CH`), and Belgian Dutch (`nl_BE`).
- Use Joe Bloggs, Max Mustermann, Juan García, Jean Dupont, Mario Rossi,
  Jan Jansen, and António Silva for the base locales, with Seán Murphy, Franz
  Huber, Hans Müller, and Jan Peeters for the regional locales. All use
  fictional `.invalid` contacts.
- Reject conflicting regional locales such as `en_US` and `pt_BR`; expose the
  package through opt-in `anonymizer.personas.enabled` and `.locale` settings.
- Publish the following fixed persona strategy IDs: `persona.name`,
  `persona.first_name`, `persona.last_name`, `persona.email`,
  `persona.username`, `persona.company`, `persona.phone`, `persona.street`,
  `persona.street_number`, `persona.city`, `persona.postcode`, and
  `persona.country`.
- Reserve all `persona.*` IDs in the Bundle compiler pass so custom tagged
  services cannot silently replace the built-in persona strategies.

## Package, wiring, and documentation

- Update `packages/anonymizer-faker` to require the core and `fakerphp/faker`;
  keep it usable without Symfony through its public factory and strategies.
- Make `symfony/yaml` a runtime dependency of
  `php-anonymizer/anonymizer-bundle`; add both adapter packages as Composer
  suggestions.
- Extend the Bundle extension to register YAML unconditionally and Faker or
  Personas strategies only for their enabled configuration. Faker strategies
  share one configured generator; Personas use stable fixed records.
- Write this refined plan to
  `implement/refined-iteration-06-faker-yaml-integration-plan.md`; update the
  iteration roadmap, CHANGELOG, package READMEs, Bundle recipe comments, and
  root installation examples.
- Extend repository quality configuration, package validation, coverage,
  Infection, Deptrac, and Composer consumer checks to include both adapter
  packages. Add `composer consumer:faker` / `make consumer-faker` and
  `composer consumer:personas` / `make consumer-personas` for isolated
  installations; keep the Bundle consumer focused on its YAML path.

## Test plan

- Unit-test every formatter's ID, generated string result, locale construction,
  textual/integer seed normalization, and repeatable sequences from
  independently created factories.
- Run the shared payload-codec contract suite against JSON and YAML, including
  valid mappings, invalid YAML, scalar/list roots, and round-tripping
  anonymized maps.
- Integration-test Symfony configuration with enabled Faker, deterministic
  seeded results, localized output support, embedded YAML payload
  anonymization, and collision rejection for `yaml` / `faker.*`.
- Verify that disabled Faker leaves its strategies unavailable and that
  enabling it without the adapter package reports the actionable dependency
  error in an isolated consumer.
- Verify every European persona record, locale normalization/rejection,
  stable `persona.*` values, Bundle opt-in wiring, and missing-package errors.
- Require the full repository quality gate, 100% line/branch coverage, and the
  existing 90% covered-MSI threshold.

## Assumptions

- Faker output is deterministic for the same locale, normalized seed, Faker
  version, and invocation order; literal generated values are not a
  cross-version compatibility contract.
- Arbitrary Faker formatter names, legacy camel-case strategy aliases, YAML
  rule-file imports, object serialization, and custom Faker providers remain
  out of scope for this iteration.
