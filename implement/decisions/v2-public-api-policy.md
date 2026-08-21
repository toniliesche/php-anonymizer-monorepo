# Decision: PHP Anonymizer 2.0 Public API Policy

## Status

Accepted for Iteration 18 planning; implementation begins in Iterations 19–21.

## Decision

PHP Anonymizer 2.0 has a curated public surface. A symbol is covered by the
2.0 semver contract only when it is listed in `docs/api-surface-v2.md`.
Autoloadability, a public method, or incidental test usage does not establish a
public API guarantee.

The supported surface has five categories:

1. one generic anonymization façade;
2. explicit extension ports and their required context/value types;
3. immutable profile, configuration, result, and report values;
4. documented exceptions callers may catch; and
5. framework discovery entry points.

Registries, traversal and policy classes, compilers, concrete built-ins,
framework wiring helpers, and legacy-import collaborators are internal. They
may be renamed, split, or removed without a semver guarantee once the v2
replacement is documented.

## Consequences

- Iteration 19 replaces the multi-argument factory and direct engine
  construction with a fluent builder and consolidates capability interfaces
  behind one generic façade.
- Iteration 19 adds explicit object-adapter and extension-module ports. The
  optional Serializer package remains the object implementation; the core does
  not gain a framework dependency.
- Iteration 20 can split the current façade, traversal, profile, Symfony, and
  Laravel implementations without exposing new implementation classes.
- Iteration 21 annotates internal classes with `@internal` and adds architecture
  checks against accidental use. Public code uses the API index rather than an
  `@api` annotation.
- Profile definitions, metadata and inspection results, rule/schema values,
  limits, anonymization contexts, result/report values, and documented
  catchable exceptions remain public contracts.
- The Symfony legacy importer command remains available during 2.x and is
  deprecated for removal in 3.0. Its collaborators are internal.
- The 2.0 release is a clean break. No compatibility aliases or deprecated
  factory shims are retained for replaced APIs.

## Public dependency rules

- Extension packages may depend only on API-indexed core symbols.
- Framework integrations may depend on core internals only inside their own
  package wiring; application code must use the façade, builder, and documented
  extension ports.
- Examples, READMEs, consumer fixtures, and guides must not construct concrete
  engines or registries.
- New public symbols require an API-index entry, a contract test, and a
  changelog/migration note when they affect a released package.

## Review gate

Before Iteration 19 starts, every production symbol must have one disposition
in the Iteration 18 inventory: retain, replace, internalize, or remove. The
replacement target and owning iteration must be unambiguous for every
transitional v1 symbol.
