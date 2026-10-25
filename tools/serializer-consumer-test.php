<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-serializer-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0o777, true);

try {
    $composer = [
        'name' => 'php-anonymizer/serializer-consumer-fixture',
        'require' => [
            'php' => '^8.5',
            'php-anonymizer/anonymizer-symfony-serializer' => '@dev',
            'symfony/property-access' => '^8.0',
        ],
        'repositories' => [
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-symfony-serializer', 'options' => ['symlink' => false]],
        ],
        'minimum-stability' => 'dev',
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
        <?php
        declare(strict_types=1);

        require __DIR__ . '/vendor/autoload.php';

        use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
        use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
        use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
        use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
        use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
        use Symfony\Component\Serializer\Serializer;

        final class ConsumerPerson
        {
            public function __construct(public string $name) {}
        }

        $anonymizer = AnonymizerBuilder::create()
            ->withProfile(ProfileDefinition::fromArray('person', [
                'mode' => 'redact',
                'rules' => [['field' => 'name']],
            ]))
            ->withExtension(new SymfonySerializerExtension(new Serializer([new ObjectNormalizer()])))
            ->build();
        $source = new ConsumerPerson('Ada');
        $safe = $anonymizer->anonymizeObject(new ObjectAnonymizationRequest('person', $source));
        if (!$safe instanceof ConsumerPerson || $safe->name !== '[REDACTED]' || $source->name !== 'Ada') {
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
