<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-laravel-consumer-' . bin2hex(random_bytes(6));
mkdir($directory . '/bootstrap/cache', 0o777, true);

try {
    $composer = [
        'name' => 'php-anonymizer/laravel-consumer-fixture',
        'require' => [
            'php' => '^8.5',
            'laravel/framework' => '^13.0',
            'php-anonymizer/anonymizer-laravel' => '@dev',
        ],
        'repositories' => [
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false, 'versions' => ['php-anonymizer/anonymizer' => '2.0.x-dev']]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-laravel', 'options' => ['symlink' => false]],
        ],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        require __DIR__ . '/vendor/autoload.php';

        use Illuminate\Foundation\PackageManifest;
        use Illuminate\Filesystem\Filesystem;
        use Illuminate\Foundation\Application;
        use Illuminate\Config\Repository;
        use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
        use PhpAnonymizer\AnonymizerLaravel\AnonymizerServiceProvider;

        $manifest = new PackageManifest(new Filesystem(), __DIR__, __DIR__ . '/bootstrap/cache/packages.php');
        $manifest->build();
        if (!in_array(AnonymizerServiceProvider::class, $manifest->providers(), true)) {
            exit(1);
        }

        $app = new Application(__DIR__);
        $app->instance('config', new Repository(['anonymizer' => ['profiles' => ['consumer' => [
            'mode' => 'redact',
            'rules' => [['field' => 'email']],
        ]]]]));
        $app->register(AnonymizerServiceProvider::class);
        $app->boot();
        $anonymizer = $app->make(AnonymizerInterface::class);
        if ($anonymizer->anonymize('consumer', ['email' => 'secret']) !== ['email' => '[REDACTED]']) {
            exit(1);
        }
        PHP);

    $command = sprintf('cd %s && composer install --no-interaction --no-progress --no-dev && php fixture.php', escapeshellarg($directory));
    passthru($command, $exitCode);

    exit($exitCode);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
