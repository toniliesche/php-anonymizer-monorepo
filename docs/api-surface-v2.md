# PHP Anonymizer 2.0 API Surface

This is the semver boundary for the 2.0 release. Only symbols listed here are
supported public APIs. Concrete implementation classes remain Composer
autoloadable for technical reasons, but applications and extension packages
must not depend on them.

## Application façade and construction

The application-facing contract is one generic `AnonymizerInterface`. It
handles array and optional object operations, payload/document operations,
batch and NDJSON processing, audit output, and contextual execution. Construct
the façade through the fluent `AnonymizerBuilder`.

The builder accepts profiles or profile providers, replacement strategies,
payload codecs, value transformations, execution limits, extension modules,
and an optional object adapter. Direct construction of `ArrayAnonymizer` and
the current `AnonymizerFactory` helpers is not part of the 2.0 contract.

The builder is immutable and reusable. Registration methods return a new
builder, and `build()` creates a façade from that builder's current
registrations. The façade keeps named array, payload, document, batch, NDJSON,
report, and audit methods; contextual execution uses an optional
`AnonymizationContext` argument. Object calls that need adapter-specific
options use `ObjectAnonymizationRequest`.

## Extension ports

The following ports are open for third-party implementations:

- `ProfileProviderInterface`
- `ReplacementStrategyInterface`
- `ContextualReplacementStrategyInterface`
- `PayloadCodecInterface`
- `DocumentPayloadCodecInterface`
- `ValueTransformationInterface`
- `ContextualValueTransformationInterface`
- `ObjectAnonymizerInterface`
- `AnonymizerExtensionInterface`
- `PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension` from
  the optional Serializer package
- `PseudonymKeyProviderInterface` from the optional pseudonymization package

Extension IDs are stable, case-sensitive identifiers. Implementations must
validate their options, avoid exposing source values or secrets in diagnostics,
and provide contract tests for success, invalid input, and failure behavior.
Symfony and Laravel integrations also support the `anonymizer.extension`
discovery tag for module implementations. Tagged modules are applied after
individual extension ports; Symfony orders them by descending `priority`,
while Laravel preserves registration order.

## Public values, results, and failures

The supported value and result contracts are:

- profile definitions, metadata, rule, condition, embedded-payload, scalar,
  and transformation values used to describe anonymization rules;
- profile inspection summaries and the profile-inspection interface;
- anonymization limits and contextual execution values;
- anonymization, payload, document, audit, and NDJSON result interfaces and
  immutable result values;
- documented report/audit entries;
- profile, payload, execution-limit, context, unsupported-value, and object
  normalization/denormalization exceptions.

The executable API manifest records and checks the supported signatures.
Internal report accumulators, JSON pointers, registries, and traversal state
are not public values.

## Framework entry points

The following framework integration entry points remain public:

- Symfony `AnonymizerBundle` registration and the legacy import command;
- Laravel `AnonymizerServiceProvider` auto-discovery;
- documented package Composer metadata and published framework configuration.

Container compiler passes, service registrars, YAML codecs, profile composers,
audit adapters, and Serializer dispatch classes are implementation details.

## Optional package factories

Plain-PHP users may use the documented Faker and Personas factory entry points
to obtain registered strategies. The strategy objects, catalogs, formatter
enums, locale/field enums, and persona data structures are internal; callers
use stable scalar IDs and locale strings.

## Migration boundary

The following v1 usages are transitional and have planned replacements:

| v1 usage | 2.0 replacement |
| --- | --- |
| `AnonymizerFactory::fromArrays()` / `fromProfiles()` / `fromProfileProviders()` | Fluent `AnonymizerBuilder` |
| `new ArrayAnonymizer(...)` | Builder-created generic façade |
| Separate batch/document/NDJSON/contextual service aliases | The generic façade |
| Direct `SymfonyAnonymizer` or `SymfonyObjectAnonymizer` usage | Object adapter registered with the builder/framework integration |
| Direct registry, traversal, policy, or compiler usage | No public replacement; use the façade or extension ports |
| Legacy importer collaborators | Legacy importer command only; collaborators are internal |

## Complete symbol index

The [executable public API manifest](../tools/public-api-manifest.php) is the
canonical inventory and signature check for these symbols:

### Core

- `PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\ContextualReplacementStrategyInterface`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\ReplacementContext`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\ValueTransformationContext`
- `PhpAnonymizer\Anonymizer\Application\Exception\ContextRequired`
- `PhpAnonymizer\Anonymizer\Application\Exception\ExecutionLimitExceeded`
- `PhpAnonymizer\Anonymizer\Application\Exception\ObjectAnonymizationUnavailable`
- `PhpAnonymizer\Anonymizer\Application\Exception\PayloadCodecFailure`
- `PhpAnonymizer\Anonymizer\Application\Exception\ProfileNotFound`
- `PhpAnonymizer\Anonymizer\Application\Exception\UnsupportedValue`
- `PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Payload\DocumentPayloadCodecInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspection`
- `PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ContextualValueTransformationInterface`
- `PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ValueTransformationInterface`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditEntry`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\DocumentAnonymizationResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\DocumentPayloadAnonymizationResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAnonymizationResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAuditResult`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\PayloadAnonymizationResult`
- `PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition`
- `PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition`
- `PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadata`
- `PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMode`
- `PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRoot`
- `PhpAnonymizer\Anonymizer\Domain\Rule\Condition\ConditionDefinition`
- `PhpAnonymizer\Anonymizer\Domain\Rule\Payload\EmbeddedPayloadDefinition`
- `PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition`
- `PhpAnonymizer\Anonymizer\Domain\Rule\ScalarAction`
- `PhpAnonymizer\Anonymizer\Domain\Rule\TransformationDefinition`

### Framework and optional packages

- `PhpAnonymizer\AnonymizerBundle\AnonymizerBundle`
- `PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand`
- `PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException`
- `PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration`
- `PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension`
- `PhpAnonymizer\AnonymizerLaravel\AnonymizerServiceProvider`
- `PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory`
- `PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory`
- `PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface`
- `PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyUnavailable`
- `PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension`
- `PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult`
- `PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectDenormalizationFailure`
- `PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectNormalizationFailure`

### Transitional compatibility types

- `PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer`
- `PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAuditProviderInterface`
