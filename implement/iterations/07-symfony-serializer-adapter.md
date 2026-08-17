# Iteration 07 — Symfony Serializer Adapter

## Goal

Add explicit, immutable object support without leaking objects into the core.

## Deliverables

- `php-anonymizer/anonymizer-symfony-serializer` adapter that normalizes an
  object, anonymizes the resulting array, and denormalizes a new instance of
  the original class.
- Context passthrough to the application-provided Symfony Serializer.
- Clear failures for normalization and denormalization errors; no internally
  constructed serializer.

## Acceptance tests

- A source object is never mutated and the returned object has the same class.
- Nested object data is anonymized through a normal profile.
- Serializer context reaches both conversion stages and failures retain useful
  diagnostics.

## Handoff

Document the adapter facade and its immutable-object guarantee.
