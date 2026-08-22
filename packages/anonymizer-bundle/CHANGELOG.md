# Changelog

## Unreleased

## 2.0.0

- Prepared the integration for the 2.0 unified façade and builder-based
  extension registration.
- Added `anonymizer.extension` module discovery with contract validation and
  priority-aware Symfony tagged iteration.
- Added focused package documentation for configuration, extensions, and
  legacy-rule migration.
- Added a complete Bundle YAML configuration example covering profiles,
  limits, Faker, and audit wiring.

## 1.7.0

- Documented explicit `anonymizer.profile_provider` consumption order for
  configured and tagged profile providers.

## 1.6.0

- Bundle configuration now supports governed profile metadata, ordered local
  fragments, startup composition diagnostics, and a public profile inspector.

## 1.5.0

- Bundle profiles now support key normalization and global scalar rules.

## 1.4.0

- Added JSON/YAML document payload support, NDJSON services, and document
  interface aliases.

## 1.3.0

- Registered core typed transformations and the optional contextual
  `date_shift` transformation through Bundle tags and aliases.

## 1.2.0

- Added optional pseudonymization configuration, key-provider wiring, and
  contextual capability aliases.

## 1.1.0

- Added execution-limit configuration and optional PSR-3 audit logging with
  safe aggregate audit context.

## 1.0.0

- Initial stable Symfony Bundle release with profile configuration and import.
