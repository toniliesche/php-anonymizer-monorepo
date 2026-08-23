<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseReadiness;
use PhpAnonymizer\Release\ReleaseTrain;

$root = dirname(__DIR__);
$version = $argv[1] ?? 'dev';

require_once $root . '/tools/Release/ReleaseReadiness.php';

require_once $root . '/tools/Release/ReleaseTrain.php';
if (preg_match('/^\d+\.\d+\.\d+$/', $version) === 1) {
    new ReleaseReadiness()->assert($version, $root);
}
$target = $root . '/var/artifacts/' . preg_replace('/[^A-Za-z0-9_.-]+/', '-', $version);
$expected = new ReleaseTrain()->packages();
$packages = [];
foreach ($expected as $name) {
    $package = $root . '/packages/' . $name;
    if (!is_dir($package) || !is_file($package . '/composer.json')) {
        throw new RuntimeException('Expected package is missing: ' . $name);
    }
    $packages[] = $package;
}
if (!is_dir($target) && !mkdir($target, 0o777, true) && !is_dir($target)) {
    throw new RuntimeException('Could not create artifact directory.');
}

$manifest = ['version' => $version, 'packages' => []];
foreach ($packages as $package) {
    $name = basename($package);
    $prefix = $name . '-' . $version . '/';
    $zipName = $name . '-' . $version . '.zip';
    $tarName = $name . '-' . $version . '.tar.gz';
    $zipPath = $target . '/' . $zipName;
    $tarPath = $target . '/' . $tarName;
    @unlink($zipPath);
    @unlink($tarPath);
    @unlink($target . '/' . $name . '-' . $version . '.tar');
    $files = packageFiles($package);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create ' . $zipPath);
    }
    foreach ($files as $relative) {
        $zip->addFile($package . '/' . $relative, $prefix . $relative);
        $zip->setMtimeName($prefix . $relative, 0);
    }
    $zip->close();

    $tar = new PharData($target . '/' . $name . '-' . $version . '.tar');
    foreach ($files as $relative) {
        $tar->addFile($package . '/' . $relative, $prefix . $relative);
    }
    $tar->compress(Phar::GZ);
    unset($tar);
    @unlink($target . '/' . $name . '-' . $version . '.tar');

    $readiness = new ReleaseReadiness();
    $required = array_merge(['README.md', 'CHANGELOG.md'], $readiness->documentationFor($name));
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not inspect ' . $zipPath);
    }
    foreach ($required as $relative) {
        if ($zip->locateName($prefix . $relative) === false) {
            throw new RuntimeException(sprintf('%s archive is missing %s.', $name, $relative));
        }
    }
    foreach (['tests/', 'phpunit.xml', 'phpstan.neon'] as $forbidden) {
        if ($zip->locateName($prefix . $forbidden) !== false) {
            throw new RuntimeException(sprintf('%s archive contains excluded %s.', $name, $forbidden));
        }
    }
    $zip->close();

    $tar = new PharData($tarPath);
    foreach ($required as $relative) {
        if (!$tar->offsetExists($prefix . $relative)) {
            throw new RuntimeException(sprintf('%s archive is missing %s.', $name, $relative));
        }
    }

    $metadata = json_decode((string) file_get_contents($package . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($metadata) || !isset($metadata['name']) || !is_string($metadata['name'])) {
        throw new RuntimeException('Invalid Composer metadata in ' . $name);
    }
    $manifest['packages'][$name] = [
        'composer_name' => $metadata['name'],
        'version' => $version,
        'zip' => $zipName,
        'zip_sha256' => hash_file('sha256', $zipPath),
        'tar_gz' => $tarName,
        'tar_gz_sha256' => hash_file('sha256', $tarPath),
    ];
}

$manifestPath = $target . '/release-manifest.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$checksums = [];
foreach (glob($target . '/*') ?: [] as $file) {
    if (is_file($file) && basename($file) !== 'SHA256SUMS') {
        $checksums[] = hash_file('sha256', $file) . '  ' . basename($file);
    }
}
sort($checksums, SORT_STRING);
file_put_contents($target . '/SHA256SUMS', implode("\n", $checksums) . "\n");
printf("Built %d package archives in %s.\n", count($packages), $target);

/**
 * @return list<string>
 */
function packageFiles(string $package): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $relative = substr($item->getPathname(), strlen($package) + 1);
        if (preg_match('#^(tests|vendor|var)/#', $relative) === 1 || in_array(basename($relative), ['phpunit.xml', 'phpstan.neon'], true)) {
            continue;
        }
        $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
    sort($files, SORT_STRING);

    return $files;
}
