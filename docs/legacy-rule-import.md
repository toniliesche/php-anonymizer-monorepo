# Importing legacy rules

The Symfony Bundle includes a one-way command for converting the supported
array-oriented legacy YAML or JSON rule format into Bundle configuration. It
does not migrate runtime services or every legacy feature. Review the result
and manually migrate unsupported behavior.

The input must contain `rules` at the document root or `anonymizer.rules`.
The rule collection may use the supported map or list form. The output is a
complete YAML configuration with an `anonymizer.profiles.legacy` profile in
`redact` mode. When the input enables Faker, the generated config carries over
its locale and seed settings; install the Faker package before enabling the
converted integration.

```sh
bin/console anonymizer:import-legacy-rules config/legacy.yaml config/packages/anonymizer.yaml
bin/console anonymizer:import-legacy-rules config/legacy.json config/packages/anonymizer.yaml --check
```

The required arguments are the input file and output file. Supported input
extensions are `.yaml`, `.yml`, and `.json`. Without `--check`, the command
writes the generated output. With `--check`, it does not write: it fails if
the output file is missing or differs byte-for-byte from the generated YAML,
and succeeds when the files match.

Object-property access in legacy rules is unsupported and causes conversion
to fail. Custom providers and application services are not recreated by the
converter; register equivalent services through the current extension ports.
Conflicting rules or malformed input also fail. The command reports conversion
errors and exits nonzero. Inspect the generated profile, especially its
`redact` mode, and adapt the profile name or rules before using it in an
application.
