<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseReadiness;
use PhpAnonymizer\Release\ReleaseTrain;

$root = dirname(__DIR__);

require_once $root . '/tools/Release/ReleaseReadiness.php';

require_once $root . '/tools/Release/ReleaseTrain.php';

$fixture = static function () use ($root): string {
    $directory = sys_get_temp_dir() . '/php-anonymizer-readiness-' . bin2hex(random_bytes(6));
    mkdir($directory, 0o777, true);
    copy($root . '/CHANGELOG.md', $directory . '/CHANGELOG.md');
    copyDirectory($root . '/packages', $directory . '/packages');

    return $directory;
};
$remove = static function (string $directory): void {
    removeDirectory($directory);
};
$readiness = new ReleaseReadiness();

$valid = $fixture();

try {
    $errors = $readiness->validate('2.0.0', $valid);
    if ($errors !== []) {
        throw new RuntimeException('Valid release fixture failed: ' . implode('; ', $errors));
    }
    $bundleManifest = json_decode((string) file_get_contents($valid . '/packages/anonymizer-bundle/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (array_key_exists('php-anonymizer/anonymizer-symfony-serializer', $bundleManifest['require'] ?? [])
        || !array_key_exists('php-anonymizer/anonymizer-symfony-serializer', $bundleManifest['suggest'] ?? [])
    ) {
        throw new RuntimeException('Valid release fixture must keep the Serializer adapter optional and suggested.');
    }
} finally {
    $remove($valid);
}

$invalidAlias = $fixture();

try {
    $manifestPath = $invalidAlias . '/packages/anonymizer/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['extra']['branch-alias']['dev-develop'] = '1.7.x-dev';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    assertErrorContains($readiness->validate('2.0.0', $invalidAlias), '2.0.x-dev branch alias');
} finally {
    $remove($invalidAlias);
}

$invalidChangelog = $fixture();

try {
    $changelogPath = $invalidChangelog . '/CHANGELOG.md';
    $contents = (string) file_get_contents($changelogPath);
    $contents = str_replace('## Unreleased', '## Notes', $contents, $count);
    if ($count !== 1) {
        throw new RuntimeException('Could not prepare changelog fixture.');
    }
    file_put_contents($changelogPath, $contents);
    assertErrorContains($readiness->validate('2.0.0', $invalidChangelog), 'Root changelog must place Unreleased');
} finally {
    $remove($invalidChangelog);
}

$invalidDocumentation = $fixture();

try {
    unlink($invalidDocumentation . '/packages/anonymizer/docs/migration-v2.md');
    assertErrorContains($readiness->validate('2.0.0', $invalidDocumentation), 'anonymizer is missing docs/migration-v2.md');
} finally {
    $remove($invalidDocumentation);
}

$invalidCoreConstraint = $fixture();

try {
    $manifestPath = $invalidCoreConstraint . '/packages/anonymizer-faker/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['require']['php-anonymizer/anonymizer'] = '^1.0';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    assertErrorContains($readiness->validate('2.0.0', $invalidCoreConstraint), 'must require php-anonymizer/anonymizer ^2.0');
} finally {
    $remove($invalidCoreConstraint);
}

$invalidSerializerConstraint = $fixture();

try {
    $manifestPath = $invalidSerializerConstraint . '/packages/anonymizer-bundle/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['require']['php-anonymizer/anonymizer-symfony-serializer'] = '^2.0';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    assertErrorContains($readiness->validate('2.0.0', $invalidSerializerConstraint), 'must keep the Serializer adapter optional');
} finally {
    $remove($invalidSerializerConstraint);
}

printf("Release readiness fixtures passed for %d packages (2.0.0).\n", count(new ReleaseTrain()->packages()));

/**
 * @param list<string> $errors
 */
function assertErrorContains(array $errors, string $expected): void
{
    foreach ($errors as $error) {
        if (str_contains($error, $expected)) {
            return;
        }
    }

    throw new RuntimeException('Expected release-readiness error was not reported: ' . $expected);
}

function copyDirectory(string $source, string $target): void
{
    mkdir($target, 0o777, true);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($item->isDir()) {
            mkdir($destination, 0o777, true);
        } else {
            copy($item->getPathname(), $destination);
        }
    }
}

function removeDirectory(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
