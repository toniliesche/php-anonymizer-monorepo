<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = $argv[1] ?? 'dev';
$target = $root . '/var/artifacts/' . preg_replace('/[^A-Za-z0-9_.-]+/', '-', $version);
$packages = glob($root . '/packages/*', GLOB_ONLYDIR) ?: [];
sort($packages);

if (is_dir($target)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
} else {
    mkdir($target, 0777, true);
}

foreach ($packages as $package) {
    $name = basename($package);
    $destination = $target . '/' . $name;
    mkdir($destination, 0777, true);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($package) + 1);
        if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, 'var/')) {
            continue;
        }

        $destinationPath = $destination . '/' . $relative;
        if ($item->isDir()) {
            mkdir($destinationPath, 0777, true);
        } else {
            copy($item->getPathname(), $destinationPath);
        }
    }
}

printf("Staged %d Composer package artifacts in %s.\n", count($packages), $target);
