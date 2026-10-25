<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-profile-provider-consumer-' . bin2hex(random_bytes(6));
$providerDirectory = $directory . '/provider-library';
mkdir($providerDirectory . '/src', 0o777, true);

try {
    file_put_contents($providerDirectory . '/composer.json', json_encode([
        'name' => 'php-anonymizer/provider-fixture',
        'require' => ['php' => '^8.5', 'php-anonymizer/anonymizer' => '@dev'],
        'autoload' => ['psr-4' => ['ProviderFixture\\' => 'src/']],
        'minimum-stability' => 'dev',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($providerDirectory . '/src/FixtureProvider.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace ProviderFixture;
        use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
        use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
        final class FixtureProvider implements ProfileProviderInterface
        {
            public function profiles(): iterable
            {
                yield ProfileDefinition::fromArray('fixture.profile', [
                    'mode' => 'redact',
                    'rules' => [['field' => 'email']],
                ]);
            }
        }
        PHP);

    $composer = [
        'name' => 'php-anonymizer/profile-provider-consumer-fixture',
        'require' => [
            'php' => '^8.5',
            'php-anonymizer/anonymizer' => '@dev',
            'php-anonymizer/provider-fixture' => '@dev',
        ],
        'repositories' => [
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false]],
            ['type' => 'path', 'url' => $providerDirectory, 'options' => ['symlink' => false]],
        ],
        'minimum-stability' => 'dev',
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        require __DIR__ . '/vendor/autoload.php';
        use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerFactory;
        use ProviderFixture\FixtureProvider;
        $result = AnonymizerFactory::fromProfileProviders([new FixtureProvider()])->anonymize('fixture.profile', ['email' => 'secret']);
        if ($result !== ['email' => '[REDACTED]']) { exit(1); }
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
