<?php

declare(strict_types=1);

use PhpAnonymizer\Release\PackagistReleaseVerifier;
use PhpAnonymizer\Release\ReleaseManifest;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseManifest.php';

require_once __DIR__ . '/Release/PackagistReleaseVerifier.php';

$manifestPath = $argv[1] ?? '';
$username = getenv('PACKAGIST_USERNAME') ?: '';
$apiToken = getenv('PACKAGIST_API_TOKEN') ?: '';

try {
    if ($manifestPath === '' || !is_file($manifestPath)) {
        throw new RuntimeException('Usage: php tools/release-packagist.php <release-manifest.json>');
    }
    $manifest = ReleaseManifest::fromJson((string) file_get_contents($manifestPath));
    $packagist = new PackagistReleaseVerifier();
    $packagist->triggerUpdates($manifest, $username, $apiToken);
    $packagist->verify($manifest);
    $packagist->installCleanConsumer($manifest);
    printf("Packagist published and consumer-verified release %s.\n", $manifest->version());
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
