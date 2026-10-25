<?php

declare(strict_types=1);

use PhpAnonymizer\Benchmark\BenchmarkComparison;

$root = dirname(__DIR__);

require_once $root . '/tools/Benchmark/BenchmarkComparison.php';

$baselinePath = $argv[1] ?? '';
$currentPath = $argv[2] ?? '';
if ($baselinePath === '' || $currentPath === '') {
    fwrite(STDERR, "Usage: php tools/check-benchmark.php BASELINE.json CURRENT.json\n");

    exit(2);
}

$decode = static function (string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException('Benchmark file not found: ' . $path);
    }
    $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($document)) {
        throw new RuntimeException('Benchmark file must contain a JSON object: ' . $path);
    }

    return $document;
};

try {
    $warnings = new BenchmarkComparison()->compare($decode($baselinePath), $decode($currentPath));
    foreach ($warnings as $warning) {
        printf("::warning title=Benchmark regression::%s\n", $warning);
        fwrite(STDERR, 'WARNING: ' . $warning . "\n");
    }
    printf("Benchmark comparison passed (%d warning(s)).\n", count($warnings));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(1);
}
