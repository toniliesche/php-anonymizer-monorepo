# Refined Iteration 18 — Public-Surface Inventory and 2.0 API Policy

## Summary

Make Iteration 18 a documentation-and-decision slice only: no production API
changes, `@internal` annotations, or compatibility code yet. Its output is the
authoritative 2.0 public-surface contract that Iterations 19–22 implement.

Create the following companion records:

- `implement/iterations/18-public-surface-inventory.md` — high-level iteration
  record.
- `implement/decisions/v2-public-api-policy.md` — binding API and semver
  decision.
- `docs/api-surface-v2.md` — developer-facing index of supported entry points
  and extension APIs.

## Inventory method and classification

- Refresh Graphify locally from the current commit and keep generated output
  uncommitted. Use it with source, documentation, consumer fixtures, framework
  registrations, and public Composer autoload namespaces to inventory every
  source symbol in the seven packages.
- Record one row per externally reachable symbol: package, FQCN, category,
  current documentation or consumer, 2.0 disposition, replacement if any, and
  owning implementation iteration.
- Use exactly five categories:
  - **Public façade** — application entry points that receive anonymization
    work.
  - **Extension port** — stable interfaces and required context/value types
    implemented by third parties.
  - **Public value/result/exception** — immutable values returned to or
    configured by callers, and exceptions callers may catch.
  - **Framework entry point** — Bundle, extension, command, or Laravel
    provider required by framework discovery.
  - **Internal** — all wiring, registries, traversal/policy code, compilers,
    built-ins, adapters, and helpers.
- Treat a class as public only when the v2 API index explicitly documents it.
  Composer autoloadability alone does not create a semver guarantee.
- Do not use `@api`; the API index is the single source of truth. Iteration 21
  applies `@internal` annotations to every internal source symbol.

## Fixed 2.0 policy decisions

- The v2 stable surface is intentionally curated. The public façade, extension
  ports, immutable profile/configuration/result values, public exceptions, and
  framework discovery entry points carry semver guarantees; all other symbols
  do not.
- `AnonymizerInterface` remains the single generic façade and is replaced—not
  extended compatibly—by the unified contract defined in Iteration 19.
- `AnonymizerFactory`, direct `ArrayAnonymizer` construction,
  capability-specific service injection, registries, traversals, policies,
  profile compilers, and concrete core built-ins are transitional v1 APIs and
  become internal or are removed in 2.0.
- The public extension catalogue for Iteration 19 is: profile providers,
  replacement strategies, payload/document codecs, value transformations,
  contextual variants, the new object adapter, and the new extension module
  contract.
- `ProfileDefinition`, profile metadata/inspection results, limits,
  anonymization contexts, result/report values, and documented catchable
  exceptions remain public only where their final v2 signature exposes them.
- Optional package factories remain public only when needed for plain-PHP
  registration; Symfony Bundle and Laravel classes remain public only as
  framework entry points.
- The legacy importer command remains a Symfony Bundle public command in 2.x,
  receives a 3.0 removal deprecation notice, and has no supported collaborator
  classes.

## Required decision artifacts

- Produce a v1-to-v2 migration table for every current public or consumer-used
  symbol. Each row must state **retain**, **replace**, **internalize**, or
  **remove**; replaced/removed rows name the exact v2 target and migration
  guide section.
- Add a public dependency rule: supported extensions may reference only
  API-indexed core symbols; framework integrations may use internal core wiring
  only within their own package.
- Add an internal dependency rule: consumers, examples, and consumer fixtures
  must use the façade/builder and documented extension ports, never concrete
  engines or registries.
- Capture Iteration 19’s fixed inputs in the ADR: one generic façade, fluent
  builder, optional object adapter, extension-module contract, no compatibility
  aliases, and synchronized 2.0 release train.
- Update the v2 architecture plan with links to the iteration record, refined
  plan, ADR, and API index.

## Verification

- Reconcile the inventory against all 120 production source files, package
  Composer entry points, framework service/container aliases, command
  registrations, README examples, extension guide examples, and consumer
  fixtures.
- Verify that every non-internal symbol has a documented purpose and every
  transitional v1 symbol has one unambiguous v2 disposition.
- Verify legacy-import command coverage and command documentation identify the
  deprecation without exposing its internal collaborators.
- Run documentation/link validation, Composer metadata validation, static
  analysis, tests, and the full `PHP_VERSION=8.5 composer qa` gate; refresh
  Graphify after the iteration’s documentation changes.
- Acceptance is complete only when another engineer can begin Iteration 19
  without deciding what is public, extensible, deprecated, or internal.

## Assumptions

- Iteration 18 changes planning, API policy, and documentation only; the actual
  annotations and source refactors begin in Iterations 19–21.
- No compatibility alias is introduced for a removed or replaced API.
- All packages release together as 2.0.0, while only behaviorally affected
  package changelogs receive release entries.
