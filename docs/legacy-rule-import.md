# Importing legacy rules

The Bundle provides a one-way migration command for the array-oriented legacy
rule format:

```console
bin/console anonymizer:import-legacy-rules config/legacy.yaml config/anonymizer.yaml
bin/console anonymizer:import-legacy-rules config/legacy.json config/anonymizer.yaml --check
```

Both YAML and JSON documents may use either a top-level `rules` map or the
legacy `anonymizer.rules` map. The command writes a complete `anonymizer:`
configuration with a `legacy` profile. It intentionally rejects object access,
custom providers, and other features that cannot be represented safely by the
v1 array profile model. Conversion is deterministic; `--check` reports missing
or changed output without modifying files.
