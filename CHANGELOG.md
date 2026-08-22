# Changelog

All notable changes to this project will be documented here.

## Unreleased

- Iteration 21: selectively removed coverage suppressions from JSON payload
  validation and transformation registration/option compilation, resolved the
  corresponding PHPStan ignore, and retained justified equivalent mutation and
  integration-boundary suppressions; added framework extension-module discovery
  and removed the legacy factory and Serializer dispatchers.
- Iteration 20: split traversal, payload, and NDJSON execution into internal
  services; removed legacy capability interfaces; unified framework wiring on
  `AnonymizerInterface`; and removed all cognitive-complexity suppressions.
- Iteration 18: documented the 2.0 public API inventory, semver boundary,
  internalization policy, and refined cleanup plan.
- Iteration 19: added the immutable builder, unified façade operations, and
  object/extension module contracts for the 2.0 construction path.

## 1.7.0

- Iteration 18: added `php-anonymizer/anonymizer-laravel` with Laravel 13 service-provider
  discovery, ordered tagged extension composition, published configuration,
  eager validation, optional integrations, and standalone Serializer support.
- Added `AnonymizerFactory::fromProfileProviders()` for explicitly registered,
  reusable profile-provider libraries.
- Added a generated Composer package-install consumer smoke test and registered
  it in the QA consumer scripts.

## 1.6.0

- Added governed profile metadata, deterministic local rule fragments,
  startup composition diagnostics, and safe profile inspection.

## 1.5.0

- Added comparison-only ASCII key normalization and profile-local global
  scalar rules without changing original dataset keys or report paths.

## 1.4.0

- Added explicit document roots, document payload APIs, and lazy NDJSON
  anonymization with bounded safe audit aggregation.

## 1.3.0

- Added typed leaf transformations with validated registries and built-in
  `nullify`, `partial_mask`, `date_generalize`, and `numeric_bucket` handlers.
- Added contextual `date_shift` transformation support.

## 1.2.0

- Added contextual anonymization and replacement interfaces carrying scoped
  application attributes, profile, and path information.
- Added the optional keyed deterministic pseudonymization package with HMAC
  replacement and pluggable key providers.

## 1.1.0

- Added execution limits, lazy batch anonymization, and deterministic safe
  aggregate audit results.

## Iteration 9.1 — 1.0.0 Cohesion and API Refactor

- Added a typed `AnonymizationResultInterface` shared by array and Symfony object results.
- Added `AnonymizerFactory` and an application-level profile collection so callers do not
  need to construct concrete engines or infrastructure profile providers.
- Made object reports immutable through accessor methods and updated public examples.
- Extracted legacy path conversion into a focused collaborator and removed legacy
  application conversion coverage exemptions.
- Kept the single `AnonymizerInterface` service contract and existing anonymization behavior.

## 1.0.0

- Completed the clean, hexagonal rewrite through Iterations 1–9.
- Added independently installable packages and deterministic release artifacts.
- Added tag-history release planning and archive checksums.

## Iteration 8 — Legacy Rule Importer (Unreleased)

- Added `anonymizer:import-legacy-rules` for one-way migration of legacy YAML and
  JSON rule documents into complete, canonical Symfony Bundle configuration.
- Added deterministic rendering, `--check` drift detection, conversion diagnostics,
  and atomic output replacement so failed migrations never overwrite existing files.
- Explicitly reject legacy object-access/custom-provider features that cannot be
  represented by the array-first v1 profile model.

## Iteration 7 — Symfony Serializer Object Integration (Unreleased)

- Added immutable object anonymization through the application-provided Symfony
  Serializer service.
- Unified array and object operations behind the single core
  `AnonymizerInterface`; the Bundle now exposes one configured anonymizer facade.
- Added expressive normalization/denormalization failures and rejected
  `object_to_populate` to protect source-object immutability.
- Made the Serializer service a required Bundle integration and added coverage,
  architecture, static-analysis, and mutation-test scope for the adapter.

## Iteration 6 — Faker and YAML Integrations (Unreleased)

- Added optional framework-free Faker replacement strategies with stable
  `faker.*` snake-case IDs, locale support, and deterministic seeds.
- Added the Bundle-provided `yaml` payload codec backed by `symfony/yaml`.
- Added Bundle configuration and extension-ID reservations for the optional integrations.
- Added optional fixed European Personas strategies with stable `persona.*`
  IDs for UK, German, Spanish, French, Italian, Dutch, and Portuguese data.
- Added regional Personas for Ireland, Austria, Switzerland, and Belgian Dutch
  through `en_IE`, `de_AT`, `de_CH`, and `nl_BE`.

## Iteration 5 — Symfony Bundle MVP (Unreleased)

- Added the Symfony 8 Bundle with inline profile configuration, an autowireable
  core anonymizer service, tagged profile providers, replacement strategies,
  and payload codecs.
- Added compile-time extension-tag ID validation, a local Flex recipe, and a
  real Symfony kernel integration test.
- Extended static analysis, architecture checks, coverage, mutation scope, and
  package metadata checks to the Bundle.

## Iteration 4.1 — Cohesion and Context-Focused Namespace Refactor (Unreleased)

- Consolidated redaction and preservation traversal around a shared rule-tree
  engine with focused mode policies.
- Centralized JSON Pointer/report composition and separated profile reference
  validation from profile storage.
- Added focused tests for report composition and profile reference graphs.
- Kept the hexagonal Domain/Application/Infrastructure boundaries while
  grouping classes below them by profile, rule, anonymization, extension,
  traversal, policy, and validation responsibility.
- Added `Application\\Extension\\Profile`, `Payload`, and `Replacement`
  contexts instead of a generic port namespace.
- Updated tests, README examples, and internal references for the intentional
  namespace migration without changing anonymization behaviour.

## Iteration 4 — Core Extensions and JSON (Unreleased)

### Added

- Finalized enumerable profile-provider, replacement-strategy, and payload-codec
  extension ports with eager duplicate/reference/cycle validation.
- Added built-in `redact`, UTF-8 `star_mask`, and `json` implementations.
- Added top-level encoded-payload methods and embedded JSON rule processing with
  prefixed RFC 6901 report paths.

## Iteration 3 — Profile Semantics (Unreleased)

### Added

- Preserve-mode allow-list traversal for recursively redacting unselected
  strings.
- Strict `when` predicates and explicit `on_scalar` actions for mixed shapes.

## Iteration 2 — Core Traversal and Diagnostics (Unreleased)

### Added

- Recursive `children` and `each` traversal for nested array profiles.
- Immutable anonymization reports with changed and missing JSON Pointer paths.
- Strict runtime shape validation for maps, lists, and selected string values.

## Iteration 1 — Foundation and Minimal Core (Unreleased)

### Added

- Initial hexagonal core and development workspace foundation.
- Flat, schema-first array anonymization with immutable profile definitions.
- Independently installable package manifests and release artifacts.
- Composer, PHPUnit 13, PHPStan, PHP-CS-Fixer, Rector, Deptrac, Infection,
  dependency analysis, consumer-install, and release-preview quality gates.
- Package-scoped Safe and UTF-8 string-function policies.
