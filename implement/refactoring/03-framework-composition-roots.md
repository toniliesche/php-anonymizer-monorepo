# Slice 3: Framework Composition Roots

## Objective

Reduce Symfony and Laravel wiring complexity while preserving their observable
container and configuration contracts.

## Changes

- Split Symfony registration into internal registrars for legacy import,
  configured profiles, core/façade services, optional extensions, and audit.
  `AnonymizerExtension::load()` becomes orchestration only.
- Split Laravel registration into profile, core, optional-extension, and
  limits/configuration collaborators. The service provider retains only
  framework lifecycle delegation.
- Preserve all current service IDs, aliases, visibility, tags, priorities,
  optional-package discovery, configuration defaults, and error messages.
- Keep YAML codecs and audit adapters local to their framework packages. Add
  shared behavioral cases rather than a common Symfony-dependent package.
- Replace duplicated optional-extension ID lists with package catalogs where
  safe; otherwise add equality tests that make drift fail visibly.

## Tests and acceptance

- Run the shared framework contract scenarios through Symfony integration and
  Laravel Testbench.
- Test profile precedence, limits, optional modules, serializer ordering,
  audit registration, public binding resolution, and invalid configuration.
- Consumer tests and `composer qa` pass with unchanged application-facing
  configuration and container behavior.
