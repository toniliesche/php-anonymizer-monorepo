<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration;

use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class LegacyImportRegistrar
{
    public function register(ContainerBuilder $container): void
    {
        $container->setDefinition(LegacyRuleMerger::class, new Definition(LegacyRuleMerger::class));
        $container->setDefinition(LegacyRuleValueConverter::class, new Definition(LegacyRuleValueConverter::class));
        $container->setDefinition(LegacyRulePathConverter::class, new Definition(LegacyRulePathConverter::class, [new Reference(LegacyRuleValueConverter::class)]));
        $container->setDefinition(LegacyRuleNodeConverter::class, new Definition(LegacyRuleNodeConverter::class, [new Reference(LegacyRuleValueConverter::class), new Reference(LegacyRulePathConverter::class)]));
        $container->setDefinition(LegacyRuleCollectionConverter::class, new Definition(LegacyRuleCollectionConverter::class, [new Reference(LegacyRuleNodeConverter::class)]));
        $container->setDefinition(LegacyRuleConverter::class, new Definition(LegacyRuleConverter::class, [new Reference(LegacyRuleCollectionConverter::class), new Reference(LegacyRuleMerger::class)]));
        $container->setDefinition(LegacyDocumentReader::class, new Definition(LegacyDocumentReader::class));
        $container->setDefinition(BundleConfigRenderer::class, new Definition(BundleConfigRenderer::class));
        $container->setDefinition(AtomicFileWriter::class, new Definition(AtomicFileWriter::class));
        $container->setDefinition(ImportLegacyRulesCommand::class, new Definition(ImportLegacyRulesCommand::class, [
            new Reference(LegacyDocumentReader::class), new Reference(LegacyRuleConverter::class),
            new Reference(BundleConfigRenderer::class), new Reference(AtomicFileWriter::class),
        ]))->addTag('console.command');
    }
}
