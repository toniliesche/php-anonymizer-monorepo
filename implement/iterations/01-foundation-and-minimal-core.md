  # Iteration 01 — Foundation and Minimal Core

## Goal

Create a reproducible, quality-gated monorepo and deliver the smallest usable
array anonymizer.

## Deliverables

- Composer monorepo package layout for `php-anonymizer/anonymizer`, with
  placeholders for the Bundle, Faker, and Serializer packages. All packages
  share one lockstep version and release train.
- Root Composer scripts are the portable developer interface; a thin Makefile
  provides shortcuts only. Versioned PHP scripts under `tools/` contain any
  non-trivial tooling logic.
- Shared CI and commands for PHP-CS-Fixer, Rector check mode, strict/max-level
  PHPStan, Composer Dependency Analyser, Deptrac, PHPUnit, Infection, Composer
  Normalize, validation, and audit. Every pull request runs the full `composer
  qa` suite.
- Immutable programmatic profiles, named profile registration, a built-in
  `[REDACTED]` replacement strategy, and
  `AnonymizerInterface::anonymize(string $profile, array $data): array`.
- Flat targeted redaction of string fields only. The source array stays intact.

## Acceptance tests

- A clean consumer project can install the core package and redact a named field.
- Missing profiles and invalid flat profiles fail with clear exceptions.
- Input immutability and default replacement are covered by unit tests.
- All quality commands pass; establish core line and branch coverage at 100% for
  the delivered code.
- The release workflow can build independently installable Composer package
  artifacts from the monorepo at one shared version.
- `composer release:preview` proves the tag-history version calculation using a
  temporary Git repository fixture.
- Deptrac enforces core domain/application/infrastructure boundaries and the
  dependency direction from optional packages into the core. PHPStan and
  Deptrac have no baseline or blanket suppression.

## Handoff

Document the minimal programmatic usage and freeze only the interfaces required
by the next iteration.
