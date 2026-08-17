# Iteration 02 — Core Traversal and Diagnostics

## Goal

Make the core useful for realistic array payloads while retaining a small,
predictable traversal model.

## Deliverables

- Strict array-profile compilation for nested maps (`children`) and homogeneous
  lists (`each`).
- Immutable traversal that records skipped missing paths without treating them as
  errors.
- `anonymizeWithReport(string $profile, array $data): AnonymizationResult`.
- Path-aware exceptions for malformed profile trees and invalid list shapes.

## Acceptance tests

- Nested maps and lists redact only selected string leaves.
- A missing field is unchanged and appears in the result report.
- Supplying a map where `each` requires a list produces a path-aware failure.
- The existing flat-profile API remains fully green.

## Handoff

Document report fields and the `children`/`each` grammar. Do not add conditions,
preserve mode, or payload codecs yet.
