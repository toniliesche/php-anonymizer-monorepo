# Keyed pseudonymization

Install `php-anonymizer/anonymizer-pseudonymization`; Composer installs the
required core package. The package's public API currently provides
`PseudonymKeyProviderInterface`, but no public standalone factory or
registration mechanism. `HmacPseudonymStrategy` is internal: do not construct
it in application code.

In Symfony, use `php-anonymizer/anonymizer-bundle` and enable
`anonymizer.pseudonymization` with the service ID of an application-owned key
provider. In Laravel, use `php-anonymizer/anonymizer-laravel` and configure
`anonymizer.pseudonymization.enabled` plus
`anonymizer.pseudonymization.key_provider`. See those integration guides for
their full configuration.

When registered by an integration, `pseudonymize` uses a non-secret profile
`key_id` and requires contextual anonymization; add the tenant or other scope
as a string context attribute when values must be isolated by scope. HMAC
output is domain-separated by key ID, profile, field path, context attributes,
and source value. Applications own key generation, secure storage, rotation,
and availability. Key material is never read from profiles or emitted in
reports, exceptions, audit entries, or logs.

The `date_shift` transformation is also registered by the integrations. It
requires contextual anonymization with `key_id`, a `scope_attribute` naming a
string context attribute, and integer `max_days` between 1 and 36525. It shifts
RFC 3339 date strings or `DateTimeInterface` values by a deterministic number
of days in the inclusive range `-max_days` to `+max_days`; string results use
RFC 3339 with milliseconds. The same key ID, profile, path, scope attribute,
scope value, and key produce the same shift. Use the same scope for records
whose relative dates must remain aligned.

The built-in pseudonymization implementation has no supported standalone
registration API. An application can supply its own contextual strategy or
transformation through the core extension interfaces, but must not depend on
the package's internal implementation classes.

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md),
[extension author guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/extension-author-guide.md),
[Bundle guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-bundle/docs/usage.md),
and [Laravel guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/packages/anonymizer-laravel/docs/usage.md)
for integration configuration.
