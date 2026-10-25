<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseHttpFixture;
use PhpAnonymizer\Release\ReleaseReadiness;
use PhpAnonymizer\Release\ReleaseTestFixture;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseReadiness.php';

require_once __DIR__ . '/Release/ReleaseTestFixture.php';

require_once __DIR__ . '/Release/ReleaseHttpFixture.php';

$fixture = new ReleaseTestFixture();
$http = null;

try {
    $monorepo = $fixture->createMonorepo();
    $errors = new ReleaseReadiness()->validate('2.0.0', $monorepo);
    if ($errors !== []) {
        throw new RuntimeException('Monorepo fixture is not release-ready: ' . implode('; ', $errors));
    }

    $destination = $fixture->createBareDestination('anonymizer-bundle');
    $legacy = $fixture->createLegacyCore();
    $tagListing = runFixtureCommand(['git', '--git-dir', $legacy['remote'], 'tag', '--list']);
    $publishedTags = preg_split('/\R+/', trim($tagListing)) ?: [];
    if ($publishedTags !== $legacy['tags']) {
        throw new RuntimeException('Legacy core fixture did not preserve all historical tags.');
    }
    $remoteHead = runFixtureCommand(['git', '--git-dir', $legacy['remote'], 'symbolic-ref', 'HEAD']);
    if ($remoteHead !== 'refs/heads/main' || !is_dir($destination)) {
        throw new RuntimeException('Bare destinations do not have the expected main branch baseline.');
    }

    $http = new ReleaseHttpFixture($fixture->temporaryDirectory());
    $http->setResponses('POST', '/api/update-package', [
        ['status' => 503, 'body' => ['error' => 'temporary fixture failure']],
        ['status' => 202, 'body' => ['status' => 'success', 'jobs' => ['fixture-job']]],
    ]);
    $http->start();

    $responses = [];
    foreach ([0, 1] as $_attempt) {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer fixture-user:fixture-token\r\n",
            'content' => '{"repository":"https://github.com/toniliesche/php-anonymizer"}',
            'ignore_errors' => true,
            'timeout' => 3,
        ]]);
        $body = file_get_contents($http->baseUrl() . '/api/update-package', false, $context);
        $responseHeaders = http_get_last_response_headers() ?: [];
        $status = $responseHeaders[0] ?? '';
        if (!is_string($body) || preg_match('/\s(\d{3})\s/', $status, $matches) !== 1) {
            throw new RuntimeException('HTTP fixture did not return a complete response.');
        }
        $responses[] = [(int) $matches[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    }
    if ($responses[0][0] !== 503 || $responses[1][0] !== 202 || $responses[1][1]['status'] !== 'success') {
        throw new RuntimeException('HTTP fixture did not advance through its configured response sequence.');
    }

    $state = $http->state();
    if (($state['requests']['POST /api/update-package'] ?? 0) !== 2
        || count($state['observed']) !== 2
        || !$state['observed'][0]['authorization_present']
    ) {
        throw new RuntimeException('HTTP fixture did not record request metadata safely.');
    }

    echo 'Release fixtures passed (monorepo, ' . count($legacy['tags']) . " legacy tags, and local HTTP responses).\n";
} finally {
    $http?->stop();
    $fixture->cleanup();
}

/**
 * @param list<string> $command
 */
function runFixtureCommand(array $command): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start fixture verification command.');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Fixture verification command failed: ' . $output);
    }

    return trim($output);
}
