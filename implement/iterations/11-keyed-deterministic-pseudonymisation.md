# Iteration 11 — Keyed Deterministic Pseudonymisation

## Goal

Add optional irreversible keyed string pseudonymisation with stable output in
an intentional scope, without changing `ReplacementStrategyInterface` or
`AnonymizerInterface`.

## Deliverables

- Independently installable `anonymizer-pseudonymization` package with
  `PseudonymKeyProviderInterface` and HMAC-SHA-256 pseudonyms using stable,
  URL-safe, unpadded Base64 encoding.
- Additive contextual replacement and anonymizer interfaces carrying profile,
  JSON Pointer, configured key ID, and canonical application attributes.
- Domain separation across key ID, profile, path, and context so equal values
  are stable only in the intended scope; keep key material out of profiles,
  reports, audits, logs, and exceptions.
- Optional Bundle configuration and capability-dependent aliases. Keep
  reversible vaults, encryption, and key management outside the package.

## Acceptance tests

- Cover determinism, Unicode, stable encoding, key rotation, key/profile/path
  separation, tenant/scope separation, preserve mode, nested rules, embedded
  payloads, contextual reports, batches, and audits.
- Cover missing context or key IDs, empty keys, provider failures, direct
  non-contextual calls, invalid rule combinations, and conditional Bundle
  configuration/aliases.
- Assert that source values, replacements, keys, and provider exception details
  never appear in errors, reports, audit entries, or logs.

## Handoff

Release the optional package and its integration as `1.2.0` after package
consumer tests, the full quality gate, and secret-disclosure checks pass.
