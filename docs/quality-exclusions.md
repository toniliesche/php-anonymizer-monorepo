# Coverage and mutation exclusions

This register explains the source annotations that affect coverage or mutation
analysis. `phpunit.xml` includes every package `src/` directory and enables
uncovered-file reporting. Framework bootstrapping, behavior-relevant
validation, and transformations remain covered by focused contract or
integration tests.

## Coverage exclusions

`@codeCoverageIgnoreFile` is used for Laravel container bootstrapping:

- `packages/anonymizer-laravel/src/AnonymizerServiceProvider.php`
- `packages/anonymizer-laravel/src/Infrastructure/Registration/CoreRegistrar.php`
- `packages/anonymizer-laravel/src/Infrastructure/Registration/ProfileRegistrar.php`
- `packages/anonymizer-laravel/src/Infrastructure/Audit/AuditRegistrar.php`

`@codeCoverageIgnore` class or method annotations cover framework wiring and
defensive paths whose invariants are established at public boundaries:

- Serializer wiring and dispatch: `SymfonySerializerExtension` and
  `SymfonyObjectAnonymizerAdapter`.
- Optional-package absence: the pseudonymization package-missing branch in
  `OptionalIntegrationRegistrar`.
- Core immutable runtime wiring: `AnonymizerRuntime`.
- Defensive checks for impossible internal states: the non-object adapter
  result in `AnonymizerFacade`, invalid precompiled nested rules in
  `RuleDefinition` and `SelectedValueDispatcher`, and an invalid converter
  collaborator in `LegacyRuleConverter`.

Bundle/Laravel container and consumer coverage exercises framework behavior;
Serializer integration coverage exercises object normalization and
denormalization. The excluded internal wiring is not a substitute for testing
those externally visible contracts.

## Mutation exclusions

`infection.json5` enables the default mutators for all seven package source
trees. There are no file-level mutation exclusions. Individual equivalent or
unreachable mutants use `@infection-ignore-next-line` with a reason in source:

- `JsonPointer`: empty segments are skipped after trimming leading slashes.
- `ArrayAnonymizer`: report collection does not change anonymized output;
  PHP canonicalizes equivalent numeric-string array keys.
- `RuntimeAssembler`: registry consumers do not use iterable keys.
- `RedactUnselectedValues`: a string cannot enter the recursive array branch.

`composer quality:scope` checks that each mutation exclusion includes a reason
and rejects file-wide Infection suppression. Keep any new exclusion narrow and
explain why the mutant is equivalent or unreachable from valid public input.

## Removed exclusions

Laravel YAML mapping-key validation was removed because Symfony's parser
rejects or normalizes numeric keys before the codec receives them. The
pseudonymization `DateShiftTransformation` file-level coverage exclusion was
also removed; its valid shifts and option, date, scope, and key failures are
tested directly.
