# Refined Iteration 17 — Laravel Framework Integration

## Summary

Add `php-anonymizer/anonymizer-laravel` for Laravel 13 and PHP 8.5+, with
Composer auto-discovery, published configuration, explicit tagged extension
composition, eager boot-time validation, and optional standalone Symfony
Serializer support. Do not add facades or require the Symfony Bundle.

## Implementation changes

- Create the Laravel package with the core package, `illuminate/support:^13.0`,
  PSR logging, and Symfony YAML dependencies. Add Composer Laravel provider
  discovery metadata.
- Publish configuration for profiles, limits, Faker, Personas,
  pseudonymization, audit, and optional Serializer integration.
- Resolve `serializer.service` when configured; otherwise resolve
  `Symfony\\Component\\Serializer\\SerializerInterface`. Never construct a
  Serializer internally.
- Add Laravel-owned YAML and audit adapters so the package remains independent
  of the Symfony Bundle.
- Compose configured profiles before tagged services in registration order:
  `anonymizer.profile_provider`, `anonymizer.replacement_strategy`,
  `anonymizer.payload_codec`, and `anonymizer.value_transformation`.
- Materialize profiles once and reuse the resulting collection for both
  anonymization and profile inspection. Validate configuration, providers,
  extensions, duplicates, references, and optional dependencies eagerly during
  application boot.
- Always bind the core, batch, document, NDJSON, and profile-inspection
  services. Bind contextual and audit interfaces only when their features are
  enabled.
- When Serializer support is enabled, bind `SymfonyObjectAnonymizer` and
  expose `SymfonyAnonymizer` through `AnonymizerInterface`; otherwise expose
  the array anonymizer.
- Document that provider libraries register implementations explicitly and tag
  them during `register()`.
- Keep Eloquent adapters, middleware, response mutation, API Resources,
  automatic profile selection, facades, discovery scanning, provider priority,
  inheritance, and overrides out of scope.

## Testing and QA

- Add Testbench integration coverage for package discovery, configuration,
  config caching, eager boot, bindings, tagged ordering, duplicate diagnostics,
  and unchanged array/document behavior.
- Cover optional integration success and missing-dependency failures.
- Cover Serializer fallback and explicit-service precedence, DTO round trips,
  source immutability, context passthrough, and normalization/denormalization
  failures.
- Add a generated no-symlink Composer consumer fixture covering installation,
  discovery, boot, and anonymization.
- Update Composer scripts, package metadata, PHPUnit coverage sources, PHPStan,
  Deptrac, artifact lists, documentation, and relevant `1.7.0` changelogs.
- Run the full PHP 8.5 quality gate and refresh Graphify after implementation.

## Fixed decisions

- Include full Symfony-Bundle feature parity.
- Implement YAML support inside the Laravel package.
- Explicit Serializer service IDs take precedence over interface fallback.
- Invalid configuration and tagged services fail during boot.
- Use ordered Laravel container tags with explicit provider registration.
- Introduce no Laravel facade or Eloquent-specific behavior.
