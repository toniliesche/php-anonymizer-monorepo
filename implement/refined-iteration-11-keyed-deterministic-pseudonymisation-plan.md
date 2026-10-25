# Refined Iteration 11 Implementation Plan — Keyed Deterministic Pseudonymisation

## Goal

Add optional, irreversible, keyed string pseudonymisation with stable output
within an intentional scope. Keep the existing `ReplacementStrategyInterface`
and `AnonymizerInterface` unchanged; all context-aware behavior is additive.

## Optional pseudonymisation package

- Add the independently installable `anonymizer-pseudonymization` package.
- Define `PseudonymKeyProviderInterface::keyFor(string $keyId): string` so
  application code or a secret-management integration supplies key material.
  Keys must never appear in profiles, reports, audit data, logs, or exception
  messages.
- Add the `pseudonymize` strategy using HMAC-SHA-256 and URL-safe, unpadded
  Base64 output. Use domain-separated, length-prefixed inputs containing the
  version domain, configured key ID, profile name, JSON Pointer, sorted context
  attributes, and source string.
- Reject direct non-contextual calls, empty keys, and provider failures with a
  stable `PseudonymKeyUnavailable` error that does not disclose key material.

## Contextual core API

- Add immutable `AnonymizationContext` containing a canonical non-empty
  string-to-string attribute map, sorted deterministically at construction.
- Add `ContextualReplacementStrategyInterface` with a dedicated
  `ReplacementContext` carrying profile, JSON Pointer, configured key ID, and
  application attributes. The existing replacement interface remains intact.
- Add `ContextualAnonymizerInterface` methods for contextual array,
  report, encoded-payload, lazy-batch, and aggregate-audit operations.
- Propagate one context through redact and preserve modes, nested `children`,
  `each`, string-list traversal, and embedded payload processing. Ordinary APIs
  must reject profiles that select a contextual strategy without the required
  context.

## Profile and traversal semantics

- Permit an optional non-empty `key_id` on leaf strategy rules only.
- Validate that `key_id` requires a strategy and that contextual strategies
  require a key ID; reject key IDs on traversal or embedded rules and reject
  unknown/contextually incompatible strategy references during profile
  validation.
- Keep explicit strategy behavior, preserve-mode selection, conditions,
  reports, audit aggregation, and embedded report-path composition unchanged.
- Use profile name and JSON Pointer as domain-separation inputs so equal values
  can be stable in the intended field/profile scope but unlinkable across
  fields or profiles; context attributes provide tenant or application scope.
- Keep reversible vaults, encryption, and key material management outside this
  package.

## Symfony Bundle integration

- Add disabled-by-default `anonymizer.pseudonymization` configuration with an
  application key-provider service reference.
- When enabled, require the optional package and provider, register
  `HmacPseudonymStrategy` under the `pseudonymize` ID, and expose the
  contextual anonymizer alias. Reject incomplete configuration clearly.
- Keep contextual aliases capability-dependent so array-only users do not gain
  a mandatory dependency on the optional package.
- Update profile schema, package metadata, README/changelog material, and
  consumer examples to document key IDs, context attributes, installation,
  and secret-handling boundaries.

## Tests and acceptance criteria

- Cover deterministic output, fixed stable encoding, Unicode input, key
  rotation, key-ID separation, profile/path separation, tenant/scope
  separation, and repeated equal inputs.
- Cover provider exceptions, empty keys, direct strategy calls, missing
  context, missing key IDs, invalid context attributes, invalid rule
  combinations, and conditional Bundle configuration/aliases.
- Cover contextual report, payload, batch, audit, preserve-mode, nested-rule,
  and embedded-payload operations.
- Assert that source values, replacement values, keys, and provider exception
  details do not appear in reports, audit entries, logs, or errors. Run the
  normal Composer quality gate and package-install/consumer checks.

## Reconstruction basis

This plan reconstructs the delivered changes from the `iteration-11` / `1.2.0`
tag, the post-Iteration-9 roadmap, the contextual API implementation, the
optional pseudonymisation package, and the Iteration 10/11 test coverage. It
does not expand the scope into reversible pseudonymisation or future typed
privacy transformations.
