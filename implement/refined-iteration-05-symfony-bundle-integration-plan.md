# Refined Iteration 5 — Symfony Bundle Integration

## Goal

Deliver an installable Symfony 8 integration around the framework-free core.
The Bundle owns container wiring and configuration only; anonymization
semantics and extension interfaces remain in `php-anonymizer/anonymizer`.

## Implementation decisions

- Add `php-anonymizer/anonymizer-bundle` with `AnonymizerBundle`, a container
  extension, configuration tree, and compiler validation pass.
- Configure profiles inline using the existing core schema:

  ```yaml
  anonymizer:
    profiles:
      customer:
        mode: redact
        rules:
          - field: email
  ```

  Invalid definitions fail during container compilation with the profile name
  and configuration path. Profile-file imports are out of scope.
- Expose `ArrayAnonymizer` and alias `AnonymizerInterface` to it. Combine
  configured profiles with services tagged `anonymizer.profile_provider`.
- Discover custom strategies and codecs through
  `anonymizer.replacement_strategy` and `anonymizer.payload_codec`. These tags
  require a valid `id` attribute; the compiler rejects missing, duplicate, or
  built-in IDs. Core registries remain the runtime authority for implementation
  IDs and profile references.
- Add local, versioned Flex recipe artifacts: a bundle manifest and minimal
  `config/packages/anonymizer.yaml`. Publication to a Flex recipe index is a
  release concern, not an Iteration 5 dependency.

## Package and quality work

- Add Bundle PSR-4 autoloading and runtime dependencies on the core plus
  Symfony Config, DependencyInjection, and HttpKernel 8.x.
- Add a real Symfony test-kernel fixture and the development dependencies it
  needs. Expand PHPStan, Rector, Deptrac, coverage, Infection, Composer
  metadata validation, and consumer checks to the new published package.
- Keep 100% line/branch coverage for all published source and the existing 90%
  covered-MSI mutation threshold.
- Update Bundle and root documentation, the roadmap, and the iteration-based
  changelog. Explicitly exclude Faker, YAML payloads, object serialization, and
  legacy configuration from this iteration.

## Acceptance tests

- A minimal Symfony kernel loads YAML configuration, autowires the anonymizer,
  and anonymizes a configured profile.
- Custom tagged providers, strategies, and codecs are discovered; invalid,
  duplicate, and reserved tag IDs fail compilation.
- Invalid profile configuration fails with useful configuration context.
- Recipe artifacts validate, and an isolated consumer can install and execute
  the Bundle with manual registration.
- The complete repository quality gate passes.

