# Refined Implementation Plan — Iteration 2

This document refines the initial iteration brief at
`iterations/02-core-traversal-and-diagnostics.md` into an implementation-ready
increment. It deliberately leaves the original iterative roadmap unchanged.

## Goal

Extend the core package with immutable nested rule trees, strict array-shape
validation, and an immutable anonymization report. Preserve every Iteration 1
flat-profile behaviour while keeping the core array-only and hexagonal.

## Public API

Add a reporting use case to the existing application port:

```php
interface AnonymizerInterface
{
    public function anonymize(string $profile, array $data): array;

    public function anonymizeWithReport(string $profile, array $data): AnonymizationResult;
}
```

`anonymize()` delegates to `anonymizeWithReport()` and returns `data()`.

Add the immutable `AnonymizationResult` value object with these accessors:

- `data(): array`
- `anonymizedPaths(): list<string>`
- `missingPaths(): list<string>`

All report paths use RFC 6901 JSON Pointer notation. Keys escape `~` as `~0`
and `/` as `~1`; list indices are ordinary path segments. This keeps paths
unambiguous for arbitrary array keys.

## Frozen rule-tree grammar

Existing flat leaf rules remain valid:

```php
['field' => 'email']
```

A rule may contain exactly one optional traversal form:

```php
// Nested map
[
    'field' => 'customer',
    'children' => [
        ['field' => 'email'],
    ],
]

// Homogeneous list of maps
[
    'field' => 'orders',
    'each' => [
        ['field' => 'reference'],
    ],
]

// Homogeneous list of selected string leaves
[
    'field' => 'tags',
    'each' => true,
]
```

Rules are strict at every depth:

- A rule has `field` plus at most one of `children` and `each`.
- `children` is a non-empty list of rules.
- `each` is either `true` or a non-empty list of rules.
- Unknown keys, a rule with both traversal keys, malformed or empty nested rule
  lists, and duplicate sibling fields fail during profile compilation.
- Compilation failures identify the precise rule-tree location.

## Runtime behaviour and architecture

- `RuleDefinition` remains an immutable Domain value object and recursively
  compiles the rule tree. The `ProfileProviderInterface` extension port is
  unchanged.
- `ArrayAnonymizer` remains the Application use case and owns traversal and
  report assembly; no Symfony, codec, strategy, reflection, or object support
  is introduced.
- Traversal is depth-first in configured-rule order and, for lists, ascending
  list-index order. Reports therefore have deterministic ordering.
- Missing selected nodes are left unchanged and recorded once at the first
  absent path. Traversal never invents paths below a missing container.
- `children` requires a map. An empty array is accepted as an empty map so
  selected child fields can be reported as missing; a non-empty PHP list fails.
- `each` requires a PHP list. With `each: [rules]`, every element must be a map
  (an empty array is accepted as an empty map). With `each: true`, every element
  must be a string and is redacted.
- A wrong container shape or a selected non-string leaf/list element fails
  immediately with `UnsupportedValue` and its concrete JSON Pointer path.
- The caller's input is never mutated. Unselected values and missing fields are
  retained exactly as supplied.

## Tests and quality gates

- Unit-test recursive profile compilation, invalid traversal forms, nested
  sibling duplicate detection, and Iteration 1 flat-profile compatibility.
- Test nested maps, lists of maps, scalar string lists, empty maps/lists,
  deterministic changed and missing report paths, and JSON Pointer escaping.
- Test missing leaves and containers, incorrect map/list shapes, invalid
  selected scalar values, exact error paths, and input immutability.
- Maintain the established quality gate: Composer validation and audit,
  PHP-CS-Fixer, PHPStan, dependency analysis, Deptrac, Rector checks, PHPUnit
  coverage, Infection, consumer installation, release-preview testing, and
  artifact staging.

## Documentation and milestone

- Update the core package documentation with flat, nested-map, list-of-maps,
  scalar-list, report, and failure examples.
- Add Iteration 2 changes to `CHANGELOG.md`.
- Once all checks pass, commit the increment and create the annotated
  `iteration-2` milestone tag. Do not create a semantic-version tag or add a
  tracked package version; release versions continue to derive from stable tag
  history and release branches.

## Deferred work

Conditions, preserve mode, strategies, codecs, YAML, Symfony integration, and
object support remain deferred to their assigned iterations.
