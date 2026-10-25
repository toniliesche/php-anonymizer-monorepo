# PHP Anonymizer Pseudonymization

Keyed deterministic pseudonymization for PHP Anonymizer 2.0. Install it with
the core package and provide an application-owned
`PseudonymKeyProviderInterface`.

Register `HmacPseudonymStrategy`, use the `pseudonymize` strategy with a
non-secret `key_id`, and call a contextual façade method. Key material never
appears in profiles, reports, exceptions, audit entries, or logs.

See the package-local [usage guide](docs/usage.md).
