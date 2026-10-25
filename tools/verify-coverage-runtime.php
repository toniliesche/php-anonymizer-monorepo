<?php

declare(strict_types=1);

$report = __DIR__ . '/../var/coverage.xml';
if (!is_file($report) || filesize($report) === 0) {
    fwrite(STDERR, "Coverage invocation did not produce var/coverage.xml.\n");

    exit(2);
}
$document = new DOMDocument();
if (!$document->load($report) || $document->documentElement?->tagName !== 'coverage') {
    fwrite(STDERR, "Coverage invocation produced an invalid coverage report.\n");

    exit(2);
}
$classes = $document->getElementsByTagName('class')->length;
if ($classes === 0) {
    fwrite(STDERR, "Coverage invocation produced no covered package classes.\n");

    exit(2);
}
printf("Coverage runtime verified with %d package classes.\n", $classes);
