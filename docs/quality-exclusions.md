# Quality Exclusion Register

This register documents published-source coverage exclusions retained for
Iteration 27. Exclusions are limited to framework/container wiring, immutable
plumbing exercised through public contracts, optional-integration absence
branches, or defensive paths that cannot be reached through a valid public
input. Behavior-relevant validation and transformation code must remain
covered directly.

## Framework and container wiring

The following files are composition roots or registration helpers. Their
behavior is verified by Bundle/Laravel container and consumer tests:

- `packages/anonymizer-bundle/src/DependencyInjection/Registration/CoreRegistrar.php`
- `packages/anonymizer-bundle/src/DependencyInjection/Registration/ProfileRegistrar.php`
- `packages/anonymizer-bundle/src/DependencyInjection/Registration/OptionalIntegrationRegistrar.php`
- `packages/anonymizer-bundle/src/DependencyInjection/Registration/LegacyImportRegistrar.php`
- `packages/anonymizer-laravel/src/AnonymizerServiceProvider.php`
- `packages/anonymizer-laravel/src/Infrastructure/Registration/CoreRegistrar.php`
- `packages/anonymizer-laravel/src/Infrastructure/Registration/ProfileRegistrar.php`
- `packages/anonymizer-laravel/src/Infrastructure/Audit/AuditRegistrar.php`
- `packages/anonymizer-symfony-serializer/src/SymfonySerializerExtension.php`

## Internal operation and value plumbing

These immutable values, dispatchers, builders, parser collaborators, and
transitional exceptions are covered through their public façade or contract:

- Core runtime assembly, façade/builder factories, operation services, and
  traversal execution values under `packages/anonymizer/src/Application/Anonymization/`
- `ProfileDefinitionParser`, `ProfileMetadataParser`, `ProfileRuleAppender`,
  `ProfileDefinitionData`, and guarded composition diagnostics under
  `packages/anonymizer/src/Domain/Profile/`
- `TransformationDefinition`, `ValueTransformationContext`,
  `ObjectAnonymizationRequest`, and `ObjectAnonymizationUnavailable`

## Optional and defensive paths

- `AtomicFileWriter`, `LegacyDocumentReader`, `BundleConfigRenderer`, and
  legacy conversion diagnostics are protected by command-level integration
  tests; filesystem and invalid-collaborator branches are defensive only.
- `SymfonyObjectAnonymizerAdapter` is a framework adapter covered through the
  Serializer integration contract.
- `PreserveTraversalPolicy` and `RedactTraversalPolicy` accessor-only plumbing
  is covered through traversal behavior.
- Equivalent Infection mutants remain limited to documented
  `@infection-ignore-next-line` cases with an explanation on the source line.

## Removed exclusions

- Laravel YAML mapping-key validation was removed as unreachable: Symfony's
  parser rejects or normalizes numeric keys before the codec receives them.
- Pseudonymization `DateShiftTransformation` is directly tested for valid
  contextual shifts, date/option failures, scope failures, and key failures;
  its file-level exclusion was removed.
