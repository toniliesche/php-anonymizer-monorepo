# Refined Implementation Plan — Iteration 1

This document refines the initial iteration brief into an implementation-ready
increment. The original brief remains at
`iterations/01-foundation-and-minimal-core.md`.

## Goal

Create a reproducible, quality-gated monorepo and deliver the smallest usable
array anonymizer while establishing hexagonal boundaries and the schema-first
public API that later iterations extend.

## Package and architecture layout

- Use a root Composer tooling project with independently installable packages
  under `packages/`: `anonymizer`, `anonymizer-bundle`, `anonymizer-faker`, and
  `anonymizer-symfony-serializer`.
- The latter three packages are valid installable skeletons in this iteration;
  their runtime features are delivered in their assigned later iterations.
- All package manifests omit `version`, declare MIT licensing, require PHP
  `^8.5`, and participate in the lockstep release train.
- Use namespace `PhpAnonymizer\\Anonymizer` for the core package.
- Enforce this dependency direction with Deptrac:

  ```text
  Infrastructure -> Application -> Domain
  ```

- `Domain` contains immutable profile and rule value objects and depends only
  on PHP.
- `Application` owns `AnonymizerInterface` and the provisional
  `ProfileProviderInterface`, and contains the anonymization use case.
- `Infrastructure` provides `InMemoryProfileProvider` for programmatic use.
- The core has no Symfony, Faker, YAML, reflection, or object-model dependency.
  Later adapters depend on the core, never the reverse.

The provider interface is deliberately small and remains one of the three
approved extension ports finalized in Iteration 4.

## Frozen public API and schema

Expose:

```php
interface AnonymizerInterface
{
    public function anonymize(string $profile, array $data): array;
}
```

Profiles are created through an immutable schema-first value object:

```php
$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [
        ['field' => 'email'],
        ['field' => 'apiToken'],
    ],
]);

$anonymizer = new ArrayAnonymizer(
    new InMemoryProfileProvider([$profile]),
);

$result = $anonymizer->anonymize('customer', $payload);
```

Iteration 1 accepts exactly this schema:

- `mode` must be `redact`.
- `rules` must be a non-empty list.
- Each rule must contain exactly one non-empty `field` string.
- Duplicate fields, duplicate profile identifiers, malformed lists, and
  unknown keys fail before data processing.
- Profile identifiers match `^[A-Za-z][A-Za-z0-9_.-]*$`.
- Fields are top-level array keys; dots have no path meaning yet.

## Runtime behavior

- Missing and unselected fields remain unchanged.
- Selected strings become `[REDACTED]`, including empty strings.
- A selected non-string value raises a path-aware exception.
- The caller’s input array is never mutated.
- Nested rules, reports, preserve mode, conditions, custom strategies, codecs,
  and object support are deferred to later iterations.

## Tooling and CI

- Keep all development dependencies and `composer.lock` at the repository root.
- Provide Composer commands for `test`, `test:coverage`, `lint`, `fix`,
  `static`, `architecture`, `dependencies`, `refactor:check`, `rector:fix`,
  `mutation`, `validate`, `audit`, `check`, `qa`, `release:preview`, and
  `release:verify`.
- `composer qa` runs validation, audit, formatting, PHPStan, dependency
  analysis, Deptrac, Rector check, PHPUnit coverage, Infection, and an isolated
  consumer test.
- Require 100% line and branch coverage for the core and a 90% covered
  mutation score for the project. Do not use baselines or blanket suppressions.
- Make only delegates to Composer. Non-trivial release and artifact logic lives
  in versioned PHP scripts under `tools/`.
- GitHub Actions runs the quality gate on PHP 8.5 for pull requests and relevant
  branch pushes. A safe release-preview workflow exercises
  `release/X.Y.x` tag-history calculation without publishing.

## Acceptance tests

- A clean consumer project installs the core package independently and redacts a
  named field.
- Unit tests cover valid and invalid profile compilation, missing profiles,
  duplicate registrations, replacement of empty and normal strings, selected
  non-string failures, and input immutability.
- All four package manifests validate and can be staged as independently
  installable artifacts at one shared release version.
- `composer release:preview` is tested with a temporary Git repository fixture
  containing no matching tag and one or more matching stable tags.
- Deptrac verifies the domain/application/infrastructure boundaries and the
  dependency direction from optional packages into the core.

## Handoff

Document the minimal programmatic usage and freeze only
`AnonymizerInterface`, `ProfileProviderInterface`, `ProfileDefinition`, and
the flat rule schema. Iteration 2 may add nested traversal and reporting, but
must keep existing flat profiles source-compatible.
