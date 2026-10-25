# Iteration 18 — Public-Surface Inventory and 2.0 API Policy

## Goal

Freeze the supported PHP Anonymizer 2.0 surface before source refactoring.
This iteration changes planning and documentation only. It does not add
`@internal`, remove APIs, or introduce compatibility code.

## Deliverables

- A complete disposition of the 120 production PHP source files in the seven
  packages.
- A v1-to-v2 migration table for every currently documented or consumer-used
  symbol.
- The binding [v2 API policy](../decisions/v2-public-api-policy.md) and
  [developer API index](../../docs/api-surface-v2.md).
- A refined implementation plan for Iteration 19 with no unresolved public API
  ownership questions.

## Classification rules

Every source symbol receives exactly one disposition:

| Category | 2.0 meaning |
| --- | --- |
| Public façade | Application-facing anonymization entry point. |
| Extension port | Interface or context/value type implemented or consumed by third-party extensions. |
| Public value/result/exception | Documented configuration, immutable result, or catchable failure contract. |
| Framework entry point | Symfony Bundle, command, or Laravel discovery entry point. |
| Internal | Implementation, wiring, registry, compiler, built-in, adapter, or helper with no semver guarantee. |

Composer autoloadability, `public` methods, and current test usage do not make
a symbol public. The v2 API index does.

## Disposition inventory

The following is the initial disposition of every current production source
area. Where a v2 replacement is planned, the replacement is owned by the
listed iteration.

### Core application and domain

| Current area | Disposition | v2 action |
| --- | --- | --- |
| `Application/Anonymization/AnonymizerInterface.php` | Transitional façade | Replace with the unified façade in Iteration 19. |
| `Application/Anonymization/{Audit, Batch, Contextual, Document, Ndjson}*Interface.php` | Transitional capability ports | Consolidate into the unified façade in Iteration 19. |
| `Application/Anonymization/ContextualReplacementStrategyInterface.php` | Extension port | Retain as a contextual strategy contract, subject to the Iteration 19 API shape. |
| `Application/Anonymization/AnonymizationContext.php` | Extension context | Retain as a documented immutable context value. |
| `Application/Anonymization/AnonymizationLimits.php` | Public configuration | Retain as documented configuration. |
| `Application/Anonymization/Result/{AnonymizationResultInterface, AnonymizationResult, AuditAnonymizationResult, AuditEntry, DocumentAnonymizationResult, DocumentPayloadAnonymizationResult, NdjsonAnonymizationResult, NdjsonAuditResult, PayloadAnonymizationResult}.php` | Public result contract | Retain and align final return types in Iteration 19. |
| `Application/Exception/{ContextRequired, ExecutionLimitExceeded, PayloadCodecFailure, ProfileNotFound, UnsupportedValue}.php` | Public exceptions | Retain documented failure categories. |
| `Domain/Exception/InvalidProfileDefinition.php` | Public exception | Retain as the profile-schema failure contract. |
| `Domain/Profile/{ProfileDefinition, ProfileMetadata, ProfileMode, ProfileRoot}.php` | Public profile contract | Retain immutable profile definition and metadata types. |
| `Domain/Rule/{RuleDefinition, ScalarAction, TransformationDefinition}.php` | Public schema values | Retain; `ProfileDefinition` and rule accessors expose these typed values. |
| `Domain/Rule/{Condition/ConditionDefinition.php, Payload/EmbeddedPayloadDefinition.php}` | Public schema values | Retain; rule accessors expose condition and embedded-payload definitions. |
| `Application/Anonymization/Path/JsonPointer.php` | Internal value | Keep report paths as strings; internalize the pointer implementation. |
| `Application/Anonymization/{ArrayAnonymizer, AnonymizerFactory}.php` | Transitional construction/engine | Replace with `AnonymizerBuilder` and an internal engine in Iteration 19. |
| `Application/Anonymization/Policy/*` | Internal traversal policy | Internalize in Iteration 21. |
| `Application/Anonymization/Traversal/*` | Internal traversal engine | Split and internalize in Iteration 20. |
| `Application/Anonymization/{ReplacementContext, ValueTransformationContext}.php` | Extension context | Retain if required by final strategy/transformation contracts; settle signatures in Iteration 19. |
| `Application/Extension/Payload/{PayloadCodecInterface, DocumentPayloadCodecInterface}.php` | Extension ports | Retain and document codec/root semantics. |
| `Application/Extension/Payload/{JsonPayloadCodec, EmbeddedPayloadTraversal, PayloadCodecRegistry}.php` | Internal codec implementation | Internalize; select built-ins by configured IDs. |
| `Application/Extension/Profile/{ProfileProviderInterface, ProfileInspectorInterface, ProfileInspection}.php` | Extension/public inspection ports | Retain documented provider and inspection contracts. |
| `Application/Extension/Profile/{ProfileCollection, ProfileRegistry, ProfileInspector}.php` | Internal profile wiring | Internalize in Iterations 19–21. |
| `Application/Extension/Profile/Validation/*` | Internal validation | Internalize in Iteration 20/21. |
| `Application/Extension/Replacement/{ReplacementStrategyInterface, ContextualReplacementStrategyInterface}.php` | Extension ports | Retain and document ID, input, context, and error contracts. |
| `Application/Extension/Replacement/{DefaultRedactionStrategy, StarMaskStrategy, ReplacementStrategyRegistry}.php` | Internal built-ins/registry | Internalize; expose built-ins through IDs. |
| `Application/Extension/Transformation/{ValueTransformationInterface, ContextualValueTransformationInterface}.php` | Extension ports | Retain and document supported types/options/context. |
| `Application/Extension/Transformation/{CoreTransformationFactory, TransformationRegistry, DateGeneralizeTransformation, NullifyTransformation, NumericBucketTransformation, PartialMaskTransformation}.php` | Internal built-ins/registry | Internalize implementations and retain ID-based configuration. |
| `Infrastructure/Profile/InMemoryProfileProvider.php` | Convenience implementation | Internalize; examples use the builder’s profile registration. |

### Symfony Bundle

| Current area | Disposition | v2 action |
| --- | --- | --- |
| `AnonymizerBundle.php` | Framework entry point | Retain as the Symfony Bundle bootstrap. |
| `DependencyInjection/AnonymizerConfiguration.php` | Framework configuration | Retain as Bundle configuration input; align aliases with Iteration 19. |
| `DependencyInjection/AnonymizerExtension.php` | Framework entry point, transitional wiring | Retain entry-point role; split implementation in Iteration 20. |
| `DependencyInjection/Compiler/*` | Internal framework wiring | Internalize. |
| `Command/ImportLegacyRulesCommand.php` | Public migration command | Retain in 2.x with a 3.0 removal deprecation. |
| `Application/LegacyImport/LegacyImportException.php` | Public command failure | Retain for command callers. |
| `Application/LegacyImport/*Converter.php`, `LegacyRuleMerger.php` | Internal migration implementation | Internalize; no supported collaborator API. |
| `Infrastructure/LegacyImport/*`, `Infrastructure/Profile/*`, `Infrastructure/Payload/YamlPayloadCodec.php`, `Infrastructure/Audit/AuditLoggerAdapter.php` | Internal adapters | Internalize; retain only framework wiring behavior. |

### Laravel integration

| Current area | Disposition | v2 action |
| --- | --- | --- |
| `AnonymizerServiceProvider.php` | Framework entry point | Retain auto-discovered provider; split registration internals in Iteration 20. |
| `Infrastructure/Payload/YamlPayloadCodec.php` | Internal adapter | Internalize. |
| `Infrastructure/Profile/ProfileComposer.php` | Internal wiring | Internalize. |
| `Infrastructure/Audit/AuditLoggerAdapter.php` | Internal adapter | Internalize. |

### Optional packages

| Current area | Disposition | v2 action |
| --- | --- | --- |
| Faker `FakerReplacementStrategyFactory.php` | Public package factory | Retain for plain-PHP registration and expose through the extension guide. |
| Faker `FakerReplacementStrategy.php`, `FakerFormatter.php` | Internal implementation/configuration | Internalize unless the final factory signature exposes the enum. |
| Personas `PersonaReplacementStrategyFactory.php` | Public package factory | Retain for plain-PHP registration. |
| Personas `PersonaReplacementStrategy.php`, `Persona.php`, `PersonaCatalog.php` | Internal implementation | Internalize. |
| Personas `PersonaField.php`, `PersonaLocale.php` | Internal implementation/configuration | Factory inputs remain stable scalar IDs and locale strings; internalize. |
| Pseudonymization `PseudonymKeyProviderInterface.php` | Extension port | Retain as the key-management contract. |
| Pseudonymization `PseudonymKeyUnavailable.php` | Public exception | Retain as the key-provider failure contract. |
| Pseudonymization `HmacPseudonymStrategy.php`, `DateShiftTransformation.php` | Internal package implementations | Internalize; configure by stable IDs. |
| Serializer `ObjectAnonymizationResult.php` | Public result type | Retain as the object result implementation of the generic result contract. |
| Serializer `ObjectNormalizationFailure.php`, `ObjectDenormalizationFailure.php` | Public exceptions | Retain documented object failure categories. |
| Serializer `SymfonyAnonymizer.php`, `SymfonyObjectAnonymizer.php` | Internal adapters | Replace with the explicit object adapter port in Iteration 19 and internalize. |

## Migration and decision record

The v1-to-v2 table must include at least these high-impact changes:

| v1 usage | v2 disposition | Owning iteration |
| --- | --- | --- |
| `AnonymizerFactory::fromArrays/fromProfiles/fromProfileProviders()` | Replace with fluent `AnonymizerBuilder` | 19 |
| Direct `new ArrayAnonymizer(...)` | Remove from supported usage; internal engine only | 19 |
| Injecting `BatchAnonymizerInterface`, `DocumentAnonymizerInterface`, or `NdjsonAnonymizerInterface` separately | Replace with the unified façade | 19 |
| Symfony’s `SymfonyAnonymizer` concrete class | Internal adapter behind the generic façade | 19 |
| Direct registry/traversal/policy construction | Internalize with no compatibility alias | 20/21 |
| Legacy importer collaborators | Internalize; retain command only | 21 |

## Acceptance criteria

- All 120 production source files have a disposition in this record or a
  linked symbol-level appendix.
- Every retained public symbol appears in `docs/api-surface-v2.md` with a
  purpose and owning package.
- Every replaced, removed, or internalized symbol has one migration target or
  an explicit reason no migration is supported.
- The API policy ADR and Iteration 19 plan contain no unresolved public-surface
  ownership decisions.
- Documentation links, Composer metadata, tests, and the PHP 8.5 quality gate
  pass; Graphify is refreshed after the documentation changes.
