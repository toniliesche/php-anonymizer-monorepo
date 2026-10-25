<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-pseudonymization-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0o777, true);

try {
    $composer = [
        'name' => 'php-anonymizer/pseudonymization-consumer-fixture',
        'require' => [
            'php' => '^8.5',
            'php-anonymizer/anonymizer-pseudonymization' => '@dev',
        ],
        'repositories' => [
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false, 'versions' => ['php-anonymizer/anonymizer' => '2.0.x-dev']]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-pseudonymization', 'options' => ['symlink' => false]],
        ],
        'minimum-stability' => 'dev',
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
        <?php
        declare(strict_types=1);

        require __DIR__ . '/vendor/autoload.php';

        use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
        use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
        use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
        use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
        use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;

        final class ConsumerKeys implements PseudonymKeyProviderInterface
        {
            public function keyFor(string $keyId): string { return 'application-secret-' . $keyId; }
        }

        $anonymizer = AnonymizerBuilder::create()
            ->withProfile(ProfileDefinition::fromArray('customer', [
                'mode' => 'redact',
                'rules' => [['field' => 'email', 'strategy' => 'pseudonymize', 'key_id' => 'primary']],
            ]))
            ->withReplacementStrategy(new HmacPseudonymStrategy(new ConsumerKeys()))
            ->build();
        $context = new AnonymizationContext(['tenant' => 'acme']);
        $first = $anonymizer->anonymize('customer', ['email' => 'ada@example.test'], $context);
        $second = $anonymizer->anonymize('customer', ['email' => 'ada@example.test'], $context);
        if ($first !== $second || $first['email'] === 'ada@example.test') {
            exit(1);
        }
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
