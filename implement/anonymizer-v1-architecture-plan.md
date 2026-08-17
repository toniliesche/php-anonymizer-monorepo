# Anonymizer v1: Iterative Implementation Plan

The implementation is split into nine small, shippable iterations. Every
iteration must leave all affected packages installable, documented, formatted,
and green in CI. Later iterations may depend only on the public contracts and
completed acceptance criteria of earlier iterations.

| Iteration | Outcome |
| --- | --- |
| [01](iterations/01-foundation-and-minimal-core.md) | Quality baseline and minimal core redaction |
| [02](iterations/02-core-traversal-and-diagnostics.md) | Nested traversal and useful reports |
| [03](iterations/03-profile-semantics.md) | Preserve mode, conditions, and fallbacks |
| [04](iterations/04-core-extensions-and-json.md) | Extension ports and embedded JSON |
| [05](iterations/05-symfony-bundle-mvp.md) | Installable Symfony Bundle |
| [06](iterations/06-faker-and-yaml.md) | Optional Faker and YAML integrations |
| [07](iterations/07-symfony-serializer-adapter.md) | Immutable Symfony object adapter |
| [08](iterations/08-legacy-rule-importer.md) | Legacy-rule migration command |
| [09](iterations/09-release-hardening.md) | Release-quality documentation and verification |

## Architecture decisions

- [Developer tooling](decisions/developer-tooling.md)
- [Tag-history release management](decisions/tag-history-release-management.md)
- [Quality, testing, and architecture tooling](decisions/quality-testing-and-architecture-tooling.md)

The refined execution plan for the first increment is recorded separately in
[Refined Iteration 1 implementation plan](refined-iteration-01-implementation-plan.md).

## Non-negotiable engineering rules

- PHP 8.5+ and Symfony 8 are the supported platforms.
- The core processes arrays only and never mutates its input. Object handling is
  exclusively an optional Symfony Serializer adapter.
- Public extension points are interfaces and explicit IDs. Do not restore legacy
  factories, regexp runtime parsing, or mutable property/reflection access.
- Each iteration updates its changelog, public API documentation, and ADRs when
  it makes an architectural decision.
- Run PHP-CS-Fixer, Rector in check mode, strict/max-level PHPStan, Composer
  Dependency Analyser, Deptrac, Composer validation/audit, PHPUnit, and
  Infection before accepting an iteration or merging a pull request.

## Settled design decisions

- Packages are developed in one monorepo and released in lockstep with a shared
  version and release train. Release automation publishes the independently
  installable Composer packages from that source.
- Versions are calculated from stable Git tag history and `release/X.Y.x`
  branches; no tracked version file or Composer `version` field exists.
- `when` is a deliberately small, declarative condition DSL. It supports only
  relative sibling-path `equals` and `in` predicates; a missing path is a
  non-match. Regular expressions and expression-language evaluation are not
  supported.

The v1 Composer identity remains `php-anonymizer/anonymizer`, but its API and
profile syntax are intentionally breaking changes. The default replacement is
`[REDACTED]`; length-preserving star masking is opt-in.
