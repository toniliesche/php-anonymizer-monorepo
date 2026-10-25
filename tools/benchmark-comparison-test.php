<?php

declare(strict_types=1);

use PhpAnonymizer\Benchmark\BenchmarkComparison;

require_once __DIR__ . '/../tools/Benchmark/BenchmarkComparison.php';

$scenario = static fn (float $time = 10.0, int $memory = 100): array => [
    'median_ms' => $time,
    'peak_memory_bytes' => $memory,
    'output_sha256' => str_repeat('a', 64),
];
$document = static fn (array $value): array => [
    'php' => '8.5.9',
    'iterations' => 100,
    'samples' => 7,
    'scenarios' => ['wide-map' => $value],
];
$comparison = new BenchmarkComparison();

if ($comparison->compare($document($scenario()), $document($scenario())) !== []) {
    throw new RuntimeException('Equal benchmark results must not warn.');
}
if (count($comparison->compare($document($scenario()), $document($scenario(11.1, 116)))) !== 2) {
    throw new RuntimeException('Threshold regressions must produce two warnings.');
}

$expectFailure = static function (callable $callback): void {
    try {
        $callback();
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException('Expected benchmark comparison failure.');
};
$expectFailure(fn (): array => $comparison->compare($document($scenario()), $document(['median_ms' => 10.0, 'peak_memory_bytes' => 100, 'output_sha256' => str_repeat('b', 64)])));
$expectFailure(fn (): array => $comparison->compare($document($scenario()), ['php' => '8.5.9', 'iterations' => 100, 'samples' => 7, 'scenarios' => []]));
$expectFailure(fn (): array => $comparison->compare($document($scenario()), $document(['median_ms' => 0, 'peak_memory_bytes' => 100, 'output_sha256' => str_repeat('a', 64)])));

echo "Benchmark comparison fixtures passed.\n";
