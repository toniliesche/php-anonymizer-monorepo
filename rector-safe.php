<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
    ])
    ->withSets([
        __DIR__ . '/vendor/thecodingmachine/safe/rector-migrate.php',
    ]);
