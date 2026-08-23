<?php

declare(strict_types=1);

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param array<string, mixed> $data
 */
function benchmarkScenario(string $name, ProfileDefinition $profile, array $data): array
{
    $anonymizer = AnonymizerBuilder::create()->withProfile($profile)->build();
    $iterations = 100;
    $warmups = 5;
    $samples = 9;

    for ($warmup = 0; $warmup < $warmups; ++$warmup) {
        $anonymizer->anonymize($profile->name(), $data);
    }

    $measurements = [];
    $digest = '';
    for ($sample = 0; $sample < $samples; ++$sample) {
        $start = hrtime(true);
        $result = [];
        for ($iteration = 0; $iteration < $iterations; ++$iteration) {
            $result = $anonymizer->anonymize($profile->name(), $data);
        }
        $measurements[] = (hrtime(true) - $start) / 1_000_000;
        $digest = hash('sha256', serialize($result));
    }
    sort($measurements, SORT_NUMERIC);

    return [
        'name' => $name,
        'iterations' => $iterations,
        'samples' => $samples,
        'median_ms' => $measurements[intdiv($samples, 2)],
        'peak_memory_bytes' => memory_get_peak_usage(true),
        'output_sha256' => $digest,
    ];
}

$wideRules = [];
$wideData = [];
for ($index = 0; $index < 128; ++$index) {
    $field = 'field_' . $index;
    $wideRules[] = ['field' => $field];
    $wideData[$field] = 'value-' . $index;
}

$normalizedRules = [];
$normalizedData = [];
for ($index = 0; $index < 64; ++$index) {
    $normalizedRules[] = ['field' => 'customer_email_' . $index];
    $normalizedData['customerEmail' . $index] = 'value-' . $index;
}

$results = [
    benchmarkScenario('wide-map', ProfileDefinition::fromArray('wide', ['mode' => 'redact', 'rules' => $wideRules]), $wideData),
    benchmarkScenario('normalized-keys', ProfileDefinition::fromArray('normalized', ['mode' => 'redact', 'key_normalization' => true, 'rules' => $normalizedRules]), $normalizedData),
];

echo json_encode([
    'php' => PHP_VERSION,
    'generated_at' => gmdate(DATE_ATOM),
    'scenarios' => $results,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
