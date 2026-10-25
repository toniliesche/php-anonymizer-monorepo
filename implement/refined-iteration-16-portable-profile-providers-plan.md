# Refined Iteration 16 — Portable Profile Providers

This document turns [Iteration 16](iterations/16-portable-profile-providers.md)
into an implementation-ready specification for reusable, library-owned profile
providers.

## Goals and compatibility

- Add one plain-PHP factory entry point for explicitly registered profile
  providers.
- Keep `ProfileProviderInterface::profiles(): iterable` unchanged as the
  provider contract.
- Preserve the existing `fromProfiles()` behavior, eager materialization,
  validation, and exception semantics.
- Keep the feature framework-independent in core; do not add package scanning,
  provider metadata, priority metadata, profile inheritance, or overrides.

## Factory API and semantics

Add the following additive method to `AnonymizerFactory`:

```php
public static function fromProfileProviders(
    iterable $providers,
    iterable $strategies = [],
    iterable $codecs = [],
    ?AnonymizationLimits $limits = null,
    iterable $transformations = [],
): AnonymizerInterface
```

- Every item in `$providers` must be a `ProfileProviderInterface` instance.
- Enumerate providers in the caller-supplied order and flatten each provider's
  `profiles()` result in its yielded order.
- Materialize the flattened profile list once during factory construction, then
  delegate to the existing `fromProfiles()` path.
- Preserve existing duplicate-name and embedded-profile-reference validation,
  as well as strategy, codec, and transformation validation.
- Propagate exceptions thrown while enumerating providers or their profiles
  unchanged; do not introduce a wrapper exception.

No changes are made to `ProfileProviderInterface`, `ProfileDefinition`, the
rule schema, traversal behavior, or Symfony container wiring beyond documenting
and testing explicit provider consumption.

## Provider-package conventions

Document guidance for packages that ship providers:

- Depend only on the core anonymizer package for the provider contract and
  profile definitions.
- Return immutable `ProfileDefinition` instances and never expose raw source
  data, secrets, or replacement values through provider APIs or diagnostics.
- Require explicit application registration; packages must not rely on Composer
  scanning or implicit discovery.
- Prefer collision-resistant dotted profile names such as
  `vendor.package.profile`.
- Profiles are not inherited or overridden; duplicate names remain validation
  errors.

## Symfony documentation and scope

Document registration through the existing `anonymizer.profile_provider` tag.
Configured profiles are consumed first, followed by tagged providers in
Symfony's configured tag order. No new Bundle feature, package scanning, or
provider discovery is introduced. Laravel registration is deferred to
Iteration 17.

## Tests and verification

Extend factory coverage for:

- deterministic provider and profile yield order;
- generator-backed provider output;
- duplicate names across providers;
- invalid embedded-profile references; and
- exceptions thrown during provider enumeration.

Keep existing `fromProfiles()` and `fromArrays()` source- and behavior-
compatibility tests unchanged. Keep Symfony integration coverage focused on
explicit tagged-provider consumption.

Add a package-install consumer smoke test that creates a temporary local
provider-library package, installs it alongside core through Composer path
repositories without symlinks, and anonymizes data through
`fromProfileProviders()`. Register the fixture in the QA consumer scripts; the
provider package itself is generated at test time and is not committed.

Run focused PHPUnit tests, then `composer test`, static analysis, linting,
refactor checks, consumer tests, and `composer qa`. Refresh Graphify after the
implementation; generated graph output remains uncommitted.

## Documentation and release

Update the extension-author guide, core and Bundle READMEs, consumer
documentation, and package/root `Unreleased` changelog entries. State clearly
that providers are explicitly registered, return immutable definitions, must
not expose raw source data, secrets, or replacement values, and do not support
profile inheritance or overrides.

This is an additive core release on the shared release train. No migration is
required for existing factories or providers.

