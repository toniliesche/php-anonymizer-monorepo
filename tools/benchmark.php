<?php

declare(strict_types=1);

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @return array<string, array{profile: ProfileDefinition, data: array<string, mixed>, operation: string}>
 */
function benchmarkScenarios(): array
{
    $wideRules = $wideData = [];
    for ($index = 0; $index < 128; ++$index) {
        $field = 'field_' . $index;
        $wideRules[] = ['field' => $field];
        $wideData[$field] = 'value-' . $index;
    }

    $normalizedRules = $normalizedData = [];
    for ($index = 0; $index < 64; ++$index) {
        $normalizedRules[] = ['field' => 'customer_email_' . $index];
        $normalizedData['customerEmail' . $index] = 'value-' . $index;
    }

    return [
        'wide-map' => [
            'profile' => ProfileDefinition::fromArray('wide', ['mode' => 'redact', 'rules' => $wideRules]),
            'data' => $wideData,
            'operation' => 'output',
        ],
        'normalized-keys' => [
            'profile' => ProfileDefinition::fromArray('normalized', ['mode' => 'redact', 'key_normalization' => true, 'rules' => $normalizedRules]),
            'data' => $normalizedData,
            'operation' => 'output',
        ],
        'nested-map' => [
            'profile' => ProfileDefinition::fromArray('nested', ['mode' => 'redact', 'rules' => [['field' => 'items', 'each' => [['field' => 'email']]]]]),
            'data' => ['items' => array_fill(0, 32, ['email' => 'value'])],
            'operation' => 'output',
        ],
        'report' => [
            'profile' => ProfileDefinition::fromArray('report', ['mode' => 'redact', 'rules' => [['field' => 'email']]]),
            'data' => ['email' => 'value'],
            'operation' => 'report',
        ],
    ];
}

/**
 * @param array{profile: ProfileDefinition, data: array<string, mixed>, operation: string} $scenario
 */
function runBenchmarkWorker(array $scenario, int $iterations): array
{
    $anonymizer = AnonymizerBuilder::create()->withProfile($scenario['profile'])->build();
    $result = null;
    $start = hrtime(true);
    for ($iteration = 0; $iteration < $iterations; ++$iteration) {
        $result = $scenario['operation'] === 'report'
            ? $anonymizer->anonymizeWithReport($scenario['profile']->name(), $scenario['data'])->data()
            : $anonymizer->anonymize($scenario['profile']->name(), $scenario['data']);
    }

    return [
        'elapsed_ms' => (hrtime(true) - $start) / 1_000_000,
        'peak_memory_bytes' => memory_get_peak_usage(true),
        'output_sha256' => hash('sha256', serialize($result)),
    ];
}

if (isset($argv[1], $argv[2]) && $argv[1] === '--worker') {
    $scenario = benchmarkScenarios()[$argv[2]] ?? throw new InvalidArgumentException('Unknown benchmark scenario.');
    echo json_encode(runBenchmarkWorker($scenario, (int) ($argv[3] ?? 100)), JSON_THROW_ON_ERROR) . PHP_EOL;

    exit(0);
}

$iterations = (int) (getenv('BENCHMARK_ITERATIONS') ?: 100);
$samples = (int) (getenv('BENCHMARK_SAMPLES') ?: 7);
$warmups = (int) (getenv('BENCHMARK_WARMUPS') ?: 2);
$results = [];

foreach (benchmarkScenarios() as $name => $scenario) {
    for ($warmup = 0; $warmup < $warmups; ++$warmup) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name, (string) $iterations], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    $measurements = [];
    $digest = null;
    foreach (range(1, $samples) as $_) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name, (string) $iterations], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start benchmark worker.');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException(trim($error));
        }

        /** @var array{elapsed_ms: float, peak_memory_bytes: int, output_sha256: string} $measurement */
        $measurement = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $measurements[] = $measurement;
        $digest = $measurement['output_sha256'];
    }
    usort($measurements, static fn (array $left, array $right): int => $left['elapsed_ms'] <=> $right['elapsed_ms']);
    $median = $measurements[intdiv(count($measurements), 2)];
    $results[] = ['name' => $name, 'iterations' => $iterations, 'samples' => $samples, 'median_ms' => $median['elapsed_ms'], 'peak_memory_bytes' => max(array_column($measurements, 'peak_memory_bytes')), 'output_sha256' => $digest];
}

echo json_encode(['php' => PHP_VERSION, 'generated_at' => gmdate(DATE_ATOM), 'scenarios' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
