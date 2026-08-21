<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\NoSetupWithParentCallOverrideRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    // This fixture intentionally wires concrete Symfony services and mirrors
    // the runtime Kernel signature; Symfony migration renames are incorrect here.
    ->withSkipPath(__DIR__ . '/packages/anonymizer-bundle/tests/Integration/TestKernel.php')
    ->withPhpSets(
        php85: true
    )
    ->withAttributesSets(
        symfony: true,
        phpunit: true,
    )
    ->withComposerBased(
        phpunit: true,
        symfony: true,
    )
    ->withImportNames()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withConfiguredRule(
        AddOverrideAttributeToOverriddenMethodsRector::class,
        [
            'allow_override_empty_method' => true,
        ],
    )
    ->withSkip([
        // Keep explicit #[Override] attributes even when PHPUnit parent setup is called.
        NoSetupWithParentCallOverrideRector::class,
    ])
    ->withRules(
        [
            PreferPHPUnitSelfCallRector::class,
        ],
    );
