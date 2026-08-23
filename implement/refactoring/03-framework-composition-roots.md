# Iteration 25: Framework Composition Roots and Serializer Opt-In

## Objective

Reduce Symfony and Laravel wiring complexity by moving registration decisions
into small internal composition components. Preserve existing application-facing
container behavior except for the intentional serializer change: Symfony object
anonymization becomes explicitly opt-in, matching Laravel.

## Changes

### Symfony composition

- Keep `AnonymizerExtension::load()` as orchestration only.
- Extract internal registrars for legacy import, configured profiles, core
  services and limits, optional integrations, audit, and serializer wiring.
- Preserve service IDs, aliases, visibility, tags, tag priorities, registration
  order, defaults, and existing error messages outside serializer behavior.
- Add `anonymizer.serializer.enabled` (default `false`) and
  `anonymizer.serializer.service` (default `null`) to the configuration tree.
- When serializer support is disabled, do not register or require a serializer
  service. When enabled, use the configured service or fall back to the
  `serializer` service and keep the existing `SymfonySerializerExtension` ID and
  priority unchanged.
- Make serializer compiler validation conditional and report the configured
  service when it is missing.

### Laravel composition

- Keep `AnonymizerServiceProvider` limited to framework lifecycle delegation.
- Extract profile, core, optional-extension, and configuration/limits
  collaborators; retain the existing singleton bindings and boot-time eager
  resolution behavior.
- Keep Faker, Personas, pseudonymization, audit, and serializer registration
  behavior and diagnostics unchanged.

### Shared integration catalog and performance rules

- Add stable `ids(): list<string>` catalogs to the Faker and Personas strategy
  factories. Symfony and Laravel adapters consume these catalogs instead of
  maintaining framework-local ID lists.
- Simplify Symfony tag validation so built-in IDs are discovered from tagged
  built-in services. Invalid IDs and collisions with built-ins or other
  extensions must retain the current failure shape.
- Registrars run only during container construction or provider registration;
  anonymization calls must not perform configuration parsing, package discovery,
  or service lookup.
- Keep the final anonymizer binding shared and construct its runtime graph once.
  Disabled serializer support must avoid both serializer validation and adapter
  construction.

## Tests and acceptance

- Symfony tests cover disabled serializer operation without a serializer service,
  enabled default/custom service wiring, missing-service failure, profile
  precedence, limits, tags, audit, optional integrations, and public bindings.
- Laravel tests mirror the same registration scenarios, including disabled and
  enabled serializer behavior and configured-service validation.
- Factory tests verify each catalog matches the strategies created by its
  factory. Retain invalid and duplicate extension-tag coverage.
- Keep framework contract cases mirrored in package-local suites so packages
  remain independently testable without a shared framework dependency.
- Update Symfony bundle documentation, recipe configuration, affected
  changelogs, and the public API manifest for the new catalog methods.
- Run focused framework suites, `PHP_VERSION=8.5 composer test`, coverage,
  bundle/Laravel/serializer consumer checks, and `PHP_VERSION=8.5 composer qa`.

## Compatibility decision

Symfony applications that need object anonymization must now set
`anonymizer.serializer.enabled: true`. No Symfony serializer adapter service is
introduced; the existing `SymfonySerializerExtension` service and tag remain
the composition boundary when the feature is enabled.
