<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0777, true);

try {
    $composer = [
        'name' => 'php-anonymizer/consumer-fixture',
        'require' => ['php' => '^8.5', 'php-anonymizer/anonymizer' => '@dev'],
        'repositories' => [['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false]]],
        'minimum-stability' => 'dev',
    ];
    file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/fixture.php', <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
use PhpAnonymizer\Anonymizer\Application\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Infrastructure\InMemoryProfileProvider;
$profile = ProfileDefinition::fromArray('consumer', ['mode' => 'redact', 'rules' => [['field' => 'email']]]);
$result = new ArrayAnonymizer(new InMemoryProfileProvider([$profile]))->anonymize('consumer', ['email' => 'secret']);
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
