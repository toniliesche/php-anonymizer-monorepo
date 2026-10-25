<?php

declare(strict_types=1);

use PhpAnonymizer\Release\GitHubReleaseClient;
use PhpAnonymizer\Release\ReleaseHttpFixture;
use PhpAnonymizer\Release\ReleaseTestFixture;

require_once __DIR__ . '/Release/ReleaseTestFixture.php';

require_once __DIR__ . '/Release/ReleaseHttpFixture.php';

require_once __DIR__ . '/Release/GitHubReleaseClient.php';

$fixture = new ReleaseTestFixture();
$http = null;

try {
    $http = new ReleaseHttpFixture($fixture->temporaryDirectory());
    $releasePath = '/repos/toniliesche/php-anonymizer-monorepo/releases';
    $http->setResponses('GET', $releasePath . '/tags/2.0.0', [['status' => 404, 'body' => ['message' => 'Not Found']]]);
    $http->setResponses('POST', $releasePath, [[
        'status' => 201,
        'body' => ['id' => 1, 'draft' => true, 'upload_url' => $http->baseUrl() . '/uploads{?name,label}', 'assets' => []],
    ]]);
    $http->setResponses('POST', '/uploads', [['status' => 201, 'body' => ['id' => 8]]]);
    $http->setResponses('PATCH', $releasePath . '/1', [['status' => 200, 'body' => ['id' => 1, 'draft' => false]]]);
    $http->setResponses('GET', '/repos/toniliesche/php-anonymizer-monorepo/pulls', [['status' => 200, 'body' => []]]);
    $http->setResponses('POST', '/repos/toniliesche/php-anonymizer-monorepo/pulls', [['status' => 201, 'body' => ['number' => 42]]]);
    $http->start();

    $client = new GitHubReleaseClient('fixture-token', apiBase: $http->baseUrl());
    if ($client->releaseForTag('2.0.0') !== null) {
        throw new RuntimeException('Missing GitHub release lookup did not return null.');
    }
    $release = $client->createDraft('2.0.0', str_repeat('a', 40));
    $asset = $fixture->temporaryDirectory() . '/release-manifest.json';
    file_put_contents($asset, '{"schema_version":1}' . "\n");
    $client->uploadAsset($release, $asset);
    if (($client->publish($release)['draft'] ?? true) !== false) {
        throw new RuntimeException('Draft release was not published.');
    }
    $client->openOrReuseSynchronizationPullRequest();

    try {
        $client->uploadAsset(['upload_url' => $http->baseUrl() . '/uploads{?name,label}', 'assets' => [['name' => 'release-manifest.json']]], $asset);

        throw new RuntimeException('Existing release asset replacement was accepted.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'refusing to replace')) {
            throw $exception;
        }
    }

    $observed = $http->state()['observed'];
    if (count($observed) !== 6 || count(array_filter($observed, static fn (array $request): bool => !$request['authorization_present'])) !== 0) {
        throw new RuntimeException('GitHub release operations were not authenticated as expected.');
    }
    echo "GitHub release orchestration fixtures passed.\n";
} finally {
    $http?->stop();
    $fixture->cleanup();
}
