<?php

declare(strict_types=1);

$coverageFile = $argv[1] ?? dirname(__DIR__) . '/var/coverage.xml';
if (!is_file($coverageFile)) {
    fwrite(STDERR, sprintf("Coverage file not found: %s\n", $coverageFile));

    exit(2);
}

$xml = simplexml_load_file($coverageFile);
if ($xml === false) {
    fwrite(STDERR, "Coverage report is not valid XML.\n");

    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);
$conditionals = (int) ($metrics['conditionals'] ?? 0);
$coveredConditionals = (int) ($metrics['coveredconditionals'] ?? 0);

if ($statements === 0 || $coveredStatements !== $statements || $coveredConditionals !== $conditionals) {
    fwrite(STDERR, sprintf(
        "Published package coverage must be 100%% line and branch coverage (lines %d/%d, branches %d/%d).\n",
        $coveredStatements,
        $statements,
        $coveredConditionals,
        $conditionals,
    ));

    exit(1);
}

printf("Published package coverage: 100%% line and branch (%d lines, %d branches).\n", $statements, $conditionals);
