<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages/anonymizer/src',
        __DIR__ . '/packages/anonymizer/tests',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true);
