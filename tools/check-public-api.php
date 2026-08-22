<?php

declare(strict_types=1);

use PhpAnonymizer\Anonymizer\Infrastructure\Profile\InMemoryProfileProvider;

/**
 * Keep public PHP examples aligned with the explicit 2.0 boundary.
 *
 * This checks examples only; architecture enforcement remains the
 * responsibility of package rules and the API manifest.
 */
$root = dirname(__DIR__);
$forbidden = [
    'PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerFactory',
    'PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyAnonymizer',
    'PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer',
    InMemoryProfileProvider::class,
];

$files = array_merge(
    glob($root . '/README.md') ?: [],
    glob($root . '/docs/*.md') ?: [],
    glob($root . '/packages/*/README.md') ?: [],
    glob($root . '/packages/*/docs/*.md') ?: [],
    glob($root . '/tools/*-consumer-test.php') ?: [],
);
$violations = [];
foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }
    $examples = str_ends_with($file, '.md')
        ? implode(PHP_EOL, array_map(static fn (array $match): string => $match[1], preg_match_all('/```(?:php)?\s*(.*?)```/s', $contents, $matches, PREG_SET_ORDER) === false ? [] : $matches))
        : $contents;
    foreach ($forbidden as $symbol) {
        if (str_contains($examples, $symbol)) {
            $violations[] = sprintf('%s references non-public symbol %s.', substr($file, strlen($root) + 1), $symbol);
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations) . PHP_EOL);

    exit(1);
}

printf("Checked %d public-example files against the API boundary.\n", count($files));
