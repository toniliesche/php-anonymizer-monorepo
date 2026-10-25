<?php

declare(strict_types=1);

use PhpAnonymizer\CI\GiteaRuntimeVerifier;

require_once __DIR__ . '/CI/GiteaRuntimeVerifier.php';

try {
    $runtime = new GiteaRuntimeVerifier()->verify(dirname(__DIR__));
    printf("Gitea PHP runtime ready: PHP %s, Xdebug %s, %d locked extensions, git-subtree at %s.\n", $runtime['php'], $runtime['xdebug'], count($runtime['extensions']), $runtime['git_subtree']);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
