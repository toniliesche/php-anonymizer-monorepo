# Iteration 16 — Portable Profile Providers

## Goal

Make library-owned rule sets easy to distribute and consume in plain PHP and
framework integrations without replacing the stable profile-provider contract
or introducing implicit package scanning.

## Deliverables

- Keep `ProfileProviderInterface::profiles(): iterable` as the stable v1
  extension port. Providers return immutable `ProfileDefinition` instances and
  do not expose raw source data, secrets, or replacement values.
- Add additive `AnonymizerFactory::fromProfileProviders(iterable $providers,
  ...)`. Flatten providers in caller order, then apply the existing duplicate
  profile-name, profile-reference, strategy, codec, and transformation
  validation.
- Define provider-package guidance: depend on the core package, use explicit
  opt-in registration, and recommend collision-resistant dotted profile names
  such as `vendor.package.profile`.
- Document Symfony registration through the existing
  `anonymizer.profile_provider` tag. Laravel provider registration is defined
  in Iteration 17.
- Do not add Composer package scanning, provider metadata, inheritance, or
  profile overrides.

## Acceptance tests

- Cover deterministic provider order, generator-backed providers, duplicate
  names across providers, invalid references, and provider failures.
- Verify existing `fromProfiles()` behavior and source compatibility remain
  unchanged.
- Add a package-install consumer fixture representing a library that ships its
  own provider and profiles.

## Handoff

Update the extension-author guide, profile schema guidance, package examples,
changelogs, and consumer documentation. Release only after core contract,
package-install, and quality checks pass.
