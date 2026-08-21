# Iteration 17 — Laravel Framework Integration

## Goal

Provide a first-party Laravel integration for profiles, extension services,
and the existing anonymizer APIs while reusing the standalone Symfony
Serializer adapter when an application explicitly opts into it.

## Deliverables

- Add an auto-discoverable `php-anonymizer/anonymizer-laravel` package for
  current Laravel 13 and the workspace PHP 8.5 baseline. Use Laravel service
  providers and dependency injection; do not add facades.
- Publish an `anonymizer` configuration file for local profile definitions and
  execution limits. During application boot, combine configured profiles with
  explicitly tagged profile providers, replacement strategies, payload codecs,
  and transformations, preserving deterministic registration and validation.
- Bind `AnonymizerInterface` and applicable additive core interfaces to the
  constructed service. Bind contextual or optional capabilities only when the
  required extensions are installed and configured.
- Define the Laravel package convention for library authors: a library’s
  Laravel service provider registers its `ProfileProviderInterface`
  implementation and applies the shared `anonymizer.profile_provider` tag.
- Support the existing `php-anonymizer/anonymizer-symfony-serializer` package
  without requiring the Symfony bundle. When enabled, Laravel receives an
  application-supplied `Symfony\Component\Serializer\SerializerInterface`,
  binds `SymfonyObjectAnonymizer`, and exposes `SymfonyAnonymizer` as the
  object-capable `AnonymizerInterface` facade service. The package must never
  construct the Serializer internally.
- Fail clearly at boot when standalone Serializer integration is enabled but
  its packages or configured service are unavailable.
- Keep Eloquent-specific adapters, generic Laravel object normalization,
  API Resource mutation, HTTP middleware, response filtering, and automatic
  model-to-profile selection out of scope.

## Acceptance tests

- Cover Laravel package discovery, service-provider boot, configuration, config
  caching, dependency-injection bindings, tagged provider composition, and
  duplicate/invalid extension diagnostics.
- Cover a Laravel fixture using the standalone Symfony Serializer without the
  Symfony bundle: DTO round-trip, source immutability, serializer-context
  passthrough, normalization/denormalization failures, and missing-service
  diagnostics.
- Verify array and document anonymization remain unchanged when Serializer
  integration is disabled.
- Run package-install smoke tests, consumer examples, and the full quality gate
  before release.

## Handoff

Update Laravel installation/configuration documentation, the extension-author
guide, package metadata, changelogs, and compatibility notes. State explicitly
that Eloquent is not a Symfony Serializer counterpart and remains a separate
future integration decision.
