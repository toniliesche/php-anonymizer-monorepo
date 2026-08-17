# Iteration 05 — Symfony Bundle MVP

## Goal

Make profiles easy to configure and the anonymizer easy to inject into a
Symfony 8 application.

## Deliverables

- `php-anonymizer/anonymizer-bundle` with Flex recipe and one autowireable core
  anonymizer service.
- `anonymizer.profiles` YAML configuration compiled into core profiles.
- Service tags: `anonymizer.profile_provider`,
  `anonymizer.replacement_strategy`, and `anonymizer.payload_codec`.
- Container-time validation for invalid configuration and duplicate extension
  IDs, plus concise install/configuration documentation.

## Acceptance tests

- A Symfony test kernel compiles a YAML profile and anonymizes data through DI.
- Tagged custom extensions are found and duplicate tags prevent compilation.
- The Flex recipe and a minimal consumer installation work.

## Handoff

Use the application container solely for integration; the core remains Symfony
free.
