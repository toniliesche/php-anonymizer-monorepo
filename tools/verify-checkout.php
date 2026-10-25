<?php

declare(strict_types=1);

use PhpAnonymizer\CI\CheckoutVerifier;

require_once __DIR__ . '/CI/CheckoutVerifier.php';

$root = dirname(__DIR__);
$revision = getenv('CI_EVENT_SHA') ?: getenv('GITHUB_SHA') ?: getenv('GITEA_SHA') ?: '';
$baseline = getenv('CI_MAIN_BASELINE_REF') ?: 'origin/main';
$runId = getenv('GITHUB_RUN_ID') ?: getenv('GITEA_RUN_ID') ?: 'local';
$attempt = getenv('GITHUB_RUN_ATTEMPT') ?: getenv('GITEA_RUN_ATTEMPT') ?: '1';
$directoryId = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $runId . '-attempt-' . $attempt);
$diagnostics = getenv('CI_DIAGNOSTICS_DIR') ?: $root . '/var/ci-diagnostics/' . $directoryId;

try {
    if ($revision === '') {
        throw new RuntimeException('CI_EVENT_SHA, GITHUB_SHA, or GITEA_SHA must identify the event revision.');
    }
    $verified = new CheckoutVerifier()->verify($root, $revision, $baseline);
    if (!is_dir($diagnostics) && !mkdir($diagnostics, 0o700, true) && !is_dir($diagnostics)) {
        throw new RuntimeException('Could not create checkout diagnostics directory.');
    }
    $metadata = [
        'event' => getenv('GITHUB_EVENT_NAME') ?: getenv('GITEA_EVENT_NAME') ?: getenv('CI_EVENT_NAME') ?: 'unknown',
        'run_id' => $runId,
        'run_attempt' => $attempt,
        'revision' => $verified['revision'],
        'main_baseline' => $verified['baseline'],
        'main_baseline_ref' => $baseline,
        'tags_available' => count($verified['tags']),
        'verified_at' => gmdate(DATE_ATOM),
    ];
    file_put_contents($diagnostics . '/checkout.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    $githubOutput = getenv('GITHUB_OUTPUT');
    if (is_string($githubOutput) && $githubOutput !== '') {
        file_put_contents($githubOutput, 'verified_revision=' . $verified['revision'] . "\nmain_baseline=" . $verified['baseline'] . "\n", FILE_APPEND);
    }
    printf("Checkout verified: %s; baseline %s; %d tags.\n", $verified['revision'], $verified['baseline'], count($verified['tags']));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
