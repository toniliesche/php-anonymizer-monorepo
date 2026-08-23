<?php

declare(strict_types=1);

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ContextualReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ReplacementContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditEntry;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\DocumentAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\DocumentPayloadAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAuditProviderInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAuditResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\PayloadAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ValueTransformationContext;
use PhpAnonymizer\Anonymizer\Application\Exception\ContextRequired;
use PhpAnonymizer\Anonymizer\Application\Exception\ExecutionLimitExceeded;
use PhpAnonymizer\Anonymizer\Application\Exception\ObjectAnonymizationUnavailable;
use PhpAnonymizer\Anonymizer\Application\Exception\PayloadCodecFailure;
use PhpAnonymizer\Anonymizer\Application\Exception\ProfileNotFound;
use PhpAnonymizer\Anonymizer\Application\Exception\UnsupportedValue;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\DocumentPayloadCodecInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ContextualValueTransformationInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ValueTransformationInterface;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadata;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMode;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRoot;
use PhpAnonymizer\Anonymizer\Domain\Rule\Condition\ConditionDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Payload\EmbeddedPayloadDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\ScalarAction;
use PhpAnonymizer\Anonymizer\Domain\Rule\TransformationDefinition;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerLaravel\AnonymizerServiceProvider;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyUnavailable;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectDenormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectNormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;

/**
 * Executable public API inventory.
 *
 * The checker derives and compares normalized type/member descriptors for
 * every entry. Keeping the inventory explicit prevents newly added classes
 * from silently becoming part of the supported API.
 *
 * @return array{supported: list<class-string>, transitional: list<class-string>}
 */
return [
    'supported' => [
        AnonymizationContext::class,
        AnonymizationLimits::class,
        AnonymizerBuilder::class,
        AnonymizerInterface::class,
        ContextualReplacementStrategyInterface::class,
        ObjectAnonymizationRequest::class,
        ReplacementContext::class,
        ValueTransformationContext::class,
        ContextRequired::class,
        ExecutionLimitExceeded::class,
        ObjectAnonymizationUnavailable::class,
        PayloadCodecFailure::class,
        ProfileNotFound::class,
        UnsupportedValue::class,
        AnonymizerExtensionInterface::class,
        ObjectAnonymizerInterface::class,
        DocumentPayloadCodecInterface::class,
        PayloadCodecInterface::class,
        ProfileInspection::class,
        ProfileInspectorInterface::class,
        ProfileProviderInterface::class,
        ReplacementStrategyInterface::class,
        ContextualValueTransformationInterface::class,
        ValueTransformationInterface::class,
        AnonymizationResult::class,
        AnonymizationResultInterface::class,
        AuditAnonymizationResult::class,
        AuditEntry::class,
        DocumentAnonymizationResult::class,
        DocumentPayloadAnonymizationResult::class,
        NdjsonAnonymizationResult::class,
        NdjsonAuditResult::class,
        PayloadAnonymizationResult::class,
        InvalidProfileDefinition::class,
        ProfileDefinition::class,
        ProfileMetadata::class,
        ProfileMode::class,
        ProfileRoot::class,
        ConditionDefinition::class,
        EmbeddedPayloadDefinition::class,
        RuleDefinition::class,
        ScalarAction::class,
        TransformationDefinition::class,
        AnonymizerBundle::class,
        ImportLegacyRulesCommand::class,
        LegacyImportException::class,
        AnonymizerConfiguration::class,
        AnonymizerExtension::class,
        AnonymizerServiceProvider::class,
        FakerReplacementStrategyFactory::class,
        PersonaReplacementStrategyFactory::class,
        PseudonymKeyProviderInterface::class,
        PseudonymKeyUnavailable::class,
        SymfonySerializerExtension::class,
        ObjectAnonymizationResult::class,
        ObjectDenormalizationFailure::class,
        ObjectNormalizationFailure::class,
    ],
    'transitional' => [
        JsonPointer::class,
        NdjsonAuditProviderInterface::class,
    ],
    'signatures' => [
        AnonymizationContext::class => '8e8f5803bd7ff811295a7fe53dc8213561060939888e3a0fbff9f49c50f68cdf',
        AnonymizationLimits::class => 'f0eb8af22bb8112907f733a01b28d4d711864a11b10ae16454cadaddce74bcf8',
        AnonymizerBuilder::class => 'cd1840e47b8131251eba7398ee5aad5edeaef12996596cef63c3e5fded16e288',
        AnonymizerInterface::class => '7a4c4f3024aa86e917d52115620558a6539f59d3e1dc4fe4a7c9a0d7a05c95fe',
        ContextualReplacementStrategyInterface::class => '93e0df859cb4ebbcc38996ab5f92820b89f783eb76cf33e6f1f5ac6e771ed32b',
        ObjectAnonymizationRequest::class => '96c1bbedd1dac9aa1d3e9a2b978220952588c600ca5a6e096da8046c2df2e9d6',
        ReplacementContext::class => 'fff35158f5ce71d1a4a8639abcf94c8fc867ef968f47b66c82c666d775665325',
        ValueTransformationContext::class => 'bda569bbe5aae5185d572187ad9f98a4287a050c3adffbc930b265c5d974c785',
        ContextRequired::class => '56dc4e9606027f6da5becef33fd18b9eaec6669e900cc3454ca1584a3269d760',
        ExecutionLimitExceeded::class => 'ef6d16938b3e1f8700c027784e89016458b79242096c6b96220aaad643fac23b',
        ObjectAnonymizationUnavailable::class => 'b9e9648dac4e7632283961f00e29ab9f554b099b5b1f2cb54395774ac7818b8a',
        PayloadCodecFailure::class => '33ad3b8a4ac8387aa6e1681cf5d03580ceb7f36d5a98af0acc424c9841049db8',
        ProfileNotFound::class => '3484a5ff62fa40b3b7d007dc3f8a7a7fe8792bd87d13d5a82090ddec12629890',
        UnsupportedValue::class => '992af871dc08bcb922565b019d93f7b3e329c6a511755872054dae3466c03af6',
        AnonymizerExtensionInterface::class => '9c689ac2387e64bb33747a7c7d320e72b7d180fd3fc4574cbf38b9039611b25e',
        ObjectAnonymizerInterface::class => 'fe6d62b22379081ddbb0d2b8ddf1c424c3d52b9cf92871554b2cd814c9046a77',
        DocumentPayloadCodecInterface::class => '77178b2b11ae38a0d999c39d4575dc87435441b2a197cbf04a914b88471fa46c',
        PayloadCodecInterface::class => '04b96957f0b9f7d2da4cb360c0a367e4e0016dfe2ceac56ae2a9110cff56ddf0',
        ProfileInspection::class => '5ecf771764ba747bf3dc92ff04ae2ec0ed60a3a07470ca35d6056f0238269f49',
        ProfileInspectorInterface::class => '01dd6a9b6360f796d7976f44b78514226628c06092bfc7a5ad1c32d8d38cc9e1',
        ProfileProviderInterface::class => 'f13c58f0d355bd805080f2993f305fc8962cf7b663e790e1be8c8a47c4710ea7',
        ReplacementStrategyInterface::class => '2028f22e4ea05d8cd9880fb6e0593069a895807bcaf7c3281a42c80a68a5cc0a',
        ContextualValueTransformationInterface::class => '3a37efcb95937d4ac8972c7c50eeb99a01b2e6e6d6dea140788f099dc508c68a',
        ValueTransformationInterface::class => 'a96f7af898ea63e3c67a1fe432a151601cfdd42febd354c83256de8667351344',
        AnonymizationResult::class => 'eeb8c5e9dc76a63470267678d1498be184b90c031b79c3ddf2b239d7586fa5eb',
        AnonymizationResultInterface::class => '720bd3bfc4d479bf99baa8569705e327dc106172ddcecfc05d3d4ebd19725c8f',
        AuditAnonymizationResult::class => '9a6793f9e31372e571a622d3c988cfe77ccc2c7a7123fe2ada7fc2a210d3d123',
        AuditEntry::class => '4cde018c2df630a664b9d40ce194813b2c97e94af4a7fd99c6564b19869e312a',
        DocumentAnonymizationResult::class => 'e76f1f5cd1860b310881908395bff4da90042e80f5031520b34d945ce445efec',
        DocumentPayloadAnonymizationResult::class => '5e33a5932ba1dc6c272cd8a83d23c128ed3c736ddf9d472686c630279078327c',
        NdjsonAnonymizationResult::class => 'a0cd1c7055df2ad84212c05de6b8ae7af817415043108ad5b16152502ce482f8',
        NdjsonAuditResult::class => 'e862dd4800afcfd78bdf2312ac0db1a35ebf34f288c693b1c5f7240ba603f868',
        PayloadAnonymizationResult::class => 'd6b9d89cd23cf5e80c8a9de2f40f79c2f88d10dd5c2fd23c6942f8c7981899dd',
        InvalidProfileDefinition::class => 'dfae2a39732524e3746c31b0ef530ec8cdb339ecc25772ee1c6cdac475d81c25',
        ProfileDefinition::class => '99a26764e36b06e02e0d3ce475c9740091e37291eb79a1f821fe71952a47466b',
        ProfileMetadata::class => 'a3352962dd468cf34016bb690e3c6c23b3459a4baac7cde63560e6f13f1a98c9',
        ProfileMode::class => '3e7af6226b26a3b92c8c90e438ab57a56f4004e10d2c83abc19e357571d83afa',
        ProfileRoot::class => '84afe3f1115c4fce7bf274004bbe7a8e5b49e7b08137b8fcc978ace947abcbc4',
        ConditionDefinition::class => '841d4573628014221402eb1aa915bafff11b06479183266c4e7d2a6f9330a362',
        EmbeddedPayloadDefinition::class => 'f36142f8d3f09168eee95cb76cc36cfabfb56c3f43bc830f83c9af38c041db42',
        RuleDefinition::class => 'd29326b66e2fb557d175cc318278c4dacb97271eb4eade92525427fe297ef5d3',
        ScalarAction::class => '3e7af6226b26a3b92c8c90e438ab57a56f4004e10d2c83abc19e357571d83afa',
        TransformationDefinition::class => '3b8a74a3fb6cb7b5f4abe6cf6d04eebf4619423d34faf88131ccdf7ff4bbfb01',
        AnonymizerBundle::class => 'f16db1db1fcf6cd61279b427dd019fcc4c71c4a5c05df09361497f58654dbbb6',
        ImportLegacyRulesCommand::class => 'd1816b59c47666ddbc970de01eecf2e00e564a5653e92c7d6d9396bd71062124',
        LegacyImportException::class => '70f40a2f05d368396bbcb16da357e3d48b6e5d90ff52a3fc69c6a050f029c6d4',
        AnonymizerConfiguration::class => '89f930aee2bf51106937eda3be3845bde660dcc22287309e8f393f8d3e16fa09',
        AnonymizerExtension::class => '1ece443abc3efb5fd2a69006ec47c71e81ea7f70da6c5902020fc6734d17911f',
        AnonymizerServiceProvider::class => '27e27c2db63ab5c2b1a90440e6b42fa0d9081d8858506064c022fb76226b5fb0',
        FakerReplacementStrategyFactory::class => '99ebef22667a1c8acc4ba4771c6fc2bd2a01f4533e875ed97826ba8da01fa891',
        PersonaReplacementStrategyFactory::class => '7d1774351b926864dd9334a397b3102368f8da2c1a339b0e2c75ae39252d1e08',
        PseudonymKeyProviderInterface::class => 'a3ac0578d1ae7856489703ce9b23e0a38d04f9a7f7c493760d45f0963d2869bc',
        PseudonymKeyUnavailable::class => '0cf31cdb9334ec1f75f495603b1c12c2bc4e4c08ce160d00196e87a652cb0dc2',
        SymfonySerializerExtension::class => '1703f6dc5660646f429c9ccf9b3867ef77106cf294f4a807f843daaefc477cff',
        ObjectAnonymizationResult::class => 'e6b41d58811979724a4523d4cddf9f29be4dbb98e0aaa87da56e119d3058d01f',
        ObjectDenormalizationFailure::class => '043d2651d78d11f016e9664279233c06559096dbd783e8c6a4413254714395b3',
        ObjectNormalizationFailure::class => '043d2651d78d11f016e9664279233c06559096dbd783e8c6a4413254714395b3',
        JsonPointer::class => '7d819632a15d1c9b5c0d4a9f7f373fc24b65f6536885561a68f241a76f9a5122',
        NdjsonAuditProviderInterface::class => 'f59313d0b96ff44c8c2d086325eed2b100d47fd252708d6d719c67cd21f11e50',
    ],
];
