<?php

declare(strict_types=1);

use PhpAnonymizer\Release\PackagistReleaseVerifier;
use PhpAnonymizer\Release\ReleaseHttpFixture;
use PhpAnonymizer\Release\ReleaseManifest;
use PhpAnonymizer\Release\ReleaseTestFixture;
use PhpAnonymizer\Release\ReleaseTrain;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseManifest.php';

require_once __DIR__ . '/Release/ReleaseTestFixture.php';

require_once __DIR__ . '/Release/ReleaseHttpFixture.php';

require_once __DIR__ . '/Release/PackagistReleaseVerifier.php';

$fixture = new ReleaseTestFixture();
$http = null;

try {
    $train = new ReleaseTrain();
    $packages = [];
    foreach ($train->packages() as $package) {
        $packages[$package] = [
            'composer_name' => $train->composerNameFor($package),
            'repository' => $train->repositoryFor($package),
            'publication_branch' => '2.x',
            'split_sha' => str_repeat(dechex(array_search($package, $train->packages(), true) + 1), 40),
            'archives' => [
                'zip' => ['file' => $package . '-2.0.0.zip', 'sha256' => str_repeat('a', 64)],
                'tar_gz' => ['file' => $package . '-2.0.0.tar.gz', 'sha256' => str_repeat('b', 64)],
            ],
        ];
    }
    $manifest = ReleaseManifest::fromArray([
        'schema_version' => 1,
        'release' => ['version' => '2.0.0', 'branch' => 'release/2.0.x', 'main_branch' => 'main', 'source_sha' => str_repeat('f', 40)],
        'packages' => $packages,
    ]);
    $http = new ReleaseHttpFixture($fixture->temporaryDirectory());
    $updateResponses = [['status' => 503, 'body' => ['error' => 'retry']]];
    for ($response = 0; $response < count($packages) - 1; $response++) {
        $updateResponses[] = ['status' => 202, 'body' => ['status' => 'success']];
    }
    $http->setResponses('POST', '/api/update-package', $updateResponses);
    foreach ($packages as $entry) {
        $metadataPath = '/p2/' . $entry['composer_name'] . '.json';
        $version = ['version' => '2.0.0', 'source' => ['reference' => $entry['split_sha']]];
        $http->setResponses('GET', $metadataPath, [
            ['status' => 200, 'body' => ['packages' => [$entry['composer_name'] => [['version' => '2.0.0', 'source' => ['reference' => str_repeat('0', 40)]]]]]],
            ['status' => 200, 'body' => ['packages' => [$entry['composer_name'] => [$version]]]],
        ]);
    }
    $http->start();
    $waits = 0;
    $verifier = new PackagistReleaseVerifier($http->baseUrl(), static function (int $seconds) use (&$waits): void { $waits += $seconds; }, 30, 60);

    try {
        $verifier->triggerUpdates($manifest, 'fixture-user', 'fixture-token');

        throw new RuntimeException('Packagist update failure was ignored.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'POST request failed')
            || !str_contains($exception->getMessage(), 'HTTP 503')
            || !str_contains($exception->getMessage(), 'retry')
        ) {
            throw $exception;
        }
    }
    $verifier->triggerUpdates($manifest, 'fixture-user', 'fixture-token');
    $verifier->verify($manifest);
    if ($waits !== 30) {
        throw new RuntimeException('Packagist source mismatch did not trigger a polling delay.');
    }

    $firstPackage = array_key_first($packages);
    $http->setResponses('GET', '/p2/' . $packages[$firstPackage]['composer_name'] . '.json', [
        ['status' => 200, 'body' => ['packages' => [$packages[$firstPackage]['composer_name'] => [['version' => '2.0.0', 'source' => ['reference' => str_repeat('0', 40)]]]]]],
    ]);
    $timeoutVerifier = new PackagistReleaseVerifier($http->baseUrl(), static function (int $seconds): void { sleep($seconds); }, 1, 1);

    try {
        $timeoutVerifier->verify($manifest);

        throw new RuntimeException('Packagist verification timeout was ignored.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'before timeout')) {
            throw $exception;
        }
    }
    $state = $http->state();
    if (($state['requests']['POST /api/update-package'] ?? 0) !== count($packages) + 1
        || count($state['observed']) < count($packages) * 2
        || !$state['observed'][0]['authorization_present']
    ) {
        throw new RuntimeException('Packagist fixture did not record authenticated update requests.');
    }
    echo 'Packagist update and verification fixtures passed for ' . count($packages) . " packages.\n";
} finally {
    $http?->stop();
    $fixture->cleanup();
}
