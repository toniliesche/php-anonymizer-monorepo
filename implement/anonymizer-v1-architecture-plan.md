# Anonymizer v1: Iterative Implementation Plan

The implementation is split into small, shippable iterations. Every iteration
must leave all affected packages installable, documented, formatted, and green
in CI. Later iterations may depend only on the public contracts and completed
acceptance criteria of earlier iterations.

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
| [10](iterations/10-performance-limits-batch-audit.md) | Performance limits, batch processing, and safe auditability |
| [11](iterations/11-keyed-deterministic-pseudonymisation.md) | Keyed deterministic pseudonymisation |
| [12](iterations/12-typed-privacy-transformations.md) | Typed privacy transformations |
| [13](iterations/13-document-payloads-and-ndjson.md) | Document payloads and NDJSON exports |
| [14](iterations/14-key-normalization-and-global-rules.md) | Key normalization and global rules |
| [15](iterations/15-profile-governance-and-controlled-reuse.md) | Profile governance and controlled reuse |

## Architecture decisions

- [Developer tooling](decisions/developer-tooling.md)
- [Tag-history release management](decisions/tag-history-release-management.md)
- [Quality, testing, and architecture tooling](decisions/quality-testing-and-architecture-tooling.md)

The refined execution plan for the first increment is recorded separately in
[Refined Iteration 1 implementation plan](refined-iteration-01-implementation-plan.md).
The refined execution plan for the Symfony integration is recorded separately
in [Refined Iteration 5 Symfony Bundle integration plan](refined-iteration-05-symfony-bundle-integration-plan.md).

## Non-negotiable engineering rules

- PHP 8.5+ and Symfony 8 are the supported platforms.
- The core processes arrays only and never mutates its input. Object handling is
  exclusively an optional Symfony Serializer adapter.
- Public extension points are interfaces and explicit IDs. Do not restore legacy
  factories, regexp runtime parsing, or mutable property/reflection access.
- New post-1.0 capabilities remain additive: keep `AnonymizerInterface` stable
  and expose batch, audit, document, streaming, contextual, and transformation
  behavior through narrowly typed interfaces.
- Key normalization is comparison-only. It may affect rule/data matching and
  condition lookup, but must never rename dataset keys in transformed output,
  reports, audit paths, or encoded payloads. Global rules may target scalar
  values or scalar lists and must use the existing preserve/redact semantics.
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
- Document APIs explicitly distinguish map and list roots; the legacy
  map-only payload contract remains unchanged. NDJSON processing is lazy and
  retains no prior records.
- When normalized rule keys collide, an exact match against the original data
  key wins; if no exact rule exists, declaration order is deterministic. The
  original data key remains the report and output key.

## Static-analysis and test contracts

- Integration tests must inspect compiler-time aliases and definitions through
  `ContainerBuilder` or verify service resolution through
  `ContainerInterface`; they must not call compiler-only methods such as
  `ContainerInterface::getAlias()` or cast an unknown container value without a
  type assertion.
- PHPUnit assertions must add runtime value where the value is not already
  statically narrowed. Do not add redundant `assertInstanceOf()` calls for
  result methods whose declared return type already identifies the concrete
  result class; use behavioral assertions instead.

The v1 Composer identity remains `php-anonymizer/anonymizer`, but its API and
profile syntax are intentionally breaking changes. The default replacement is
`[REDACTED]`; length-preserving star masking is opt-in.
