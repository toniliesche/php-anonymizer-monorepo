# Iteration 03 — Profile Semantics

## Goal

Complete the rule semantics needed for ordinary structured code/data
anonymization.

## Deliverables

- `redact` and inverse `preserve` profile modes.
- Conditional rules through a safe, declarative `when` DSL and explicit
  scalar/container alternatives through `on_scalar`. `when` supports only
  relative sibling-path `equals` and `in` predicates; missing paths do not
  match. Regular expressions and expression evaluation are excluded.
- Validation for rule references and unsupported selected values.
- A stable public schema vocabulary shared by programmatic profiles and future
  Symfony YAML: `field`, `children`, `each`, `strategy`, `when`, `embedded`,
  and `on_scalar`.

## Acceptance tests

- Preserve mode retains selected strings and redacts other supported strings.
- Conditions cover `equals` and `in`, matching and non-matching paths, and
  missing sibling paths.
- Mixed scalar/container values require and respect `on_scalar`.
- Selected non-string values fail unless a named strategy declares support.

## Handoff

Publish the schema reference. Existing profiles stay source-compatible with the
first two iterations.
