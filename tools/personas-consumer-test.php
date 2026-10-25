<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-personas-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0o777, true);

try {
    $composer = [
        'name' => 'php-anonymizer/personas-consumer-fixture',
        'require' => ['php' => '^8.5', 'php-anonymizer/anonymizer-personas' => '@dev'],
        'repositories' => [
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-personas', 'options' => ['symlink' => false]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false, 'versions' => ['php-anonymizer/anonymizer' => '2.0.x-dev']]],
        ],
        'minimum-stability' => 'dev',
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        require __DIR__ . '/vendor/autoload.php';
        use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
        $strategies = PersonaReplacementStrategyFactory::create('es');
        if ($strategies[0]->replace('x') !== 'Juan García') { exit(1); }
        PHP);
    passthru(sprintf('cd %s && composer install --no-interaction --no-progress --no-dev && php fixture.php', escapeshellarg($directory)), $exitCode);

    exit($exitCode);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
