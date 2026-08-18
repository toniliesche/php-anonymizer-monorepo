<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->withPhpSets(php85: true)
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: true,
        removeUnusedImports: true,
    )
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true);
