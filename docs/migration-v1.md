# Historical: Migrating legacy configurations to PHP Anonymizer 1.0

This note describes the original 1.0 rewrite and is retained for historical
upgrades. It does not describe upgrading a 1.x application to the current 2.0
API; use the [2.0 migration guide](migration-v2.md) for that.

Version 1.0 is the clean rewrite of the legacy anonymizer. Configuration is
profile-first and array-based; legacy YAML/JSON rule files can be converted
with `anonymizer:import-legacy-rules` and should be reviewed before deployment.

The public API exposes one configured `Anonymizer` facade. Arrays are handled
by the core and objects by the optional Symfony Serializer integration. Objects
are normalized into a new instance and are never populated in place.

Legacy object access and custom providers have no direct v1 equivalent. Replace
them with explicit profile rules, tagged replacement strategies, or payload
codecs. Faker, Personas, YAML, and Serializer support are optional packages.
