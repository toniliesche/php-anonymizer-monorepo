<?php

declare(strict_types=1);

$coverageFile = $argv[1] ?? __DIR__ . '/../var/coverage.xml';
if (!is_file($coverageFile)) {
    fwrite(STDERR, sprintf("Coverage file not found: %s\n", $coverageFile));

    exit(2);
}

$xml = simplexml_load_file($coverageFile);
if ($xml === false) {
    fwrite(STDERR, "Coverage report is not valid XML.\n");

    exit(2);
}

$rootMetrics = $xml->attributes();
$statements = (int) ($rootMetrics['lines-valid'] ?? 0);
$coveredStatements = (int) ($rootMetrics['lines-covered'] ?? 0);

if ($statements === 0 || $coveredStatements !== $statements) {
    fwrite(STDERR, sprintf(
        "Published package coverage must be positive 100%% line coverage (lines %d/%d).\n",
        $coveredStatements,
        $statements,
    ));

    exit(1);
}

printf("Published package coverage: 100%% line coverage (%d lines). Branch/path coverage is deferred until a compatible driver is available.\n", $statements);
