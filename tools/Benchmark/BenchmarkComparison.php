<?php

declare(strict_types=1);

namespace PhpAnonymizer\Benchmark;

use RuntimeException;

final class BenchmarkComparison
{
    /**
     * @return list<string>
     */
    public function compare(array $baseline, array $current): array
    {
        $baseline = $this->validateDocument($baseline, 'baseline');
        $current = $this->validateDocument($current, 'current');

        if (!str_starts_with($current['php'], '8.5.') || !str_starts_with($baseline['php'], '8.5.')) {
            throw new RuntimeException('Benchmark baseline and current result must use PHP 8.5.');
        }
        if ($baseline['iterations'] !== $current['iterations'] || $baseline['samples'] !== $current['samples']) {
            throw new RuntimeException('Benchmark baseline and current configuration differ.');
        }
        $baselineNames = array_keys($baseline['scenarios']);
        $currentNames = array_keys($current['scenarios']);
        sort($baselineNames, SORT_STRING);
        sort($currentNames, SORT_STRING);
        if ($baselineNames !== $currentNames) {
            throw new RuntimeException('Benchmark baseline and current scenarios differ.');
        }

        $warnings = [];
        foreach ($currentNames as $name) {
            $expected = $baseline['scenarios'][$name];
            $actual = $current['scenarios'][$name];
            if ($expected['output_sha256'] !== $actual['output_sha256']) {
                throw new RuntimeException('Benchmark semantic output changed for scenario ' . $name . '.');
            }
            $timeChange = $this->relativeChange($expected['median_ms'], $actual['median_ms']);
            if ($timeChange > 0.10) {
                $warnings[] = sprintf('%s median time increased by %.1f%%.', $name, $timeChange * 100);
            }
            $memoryChange = $this->relativeChange($expected['peak_memory_bytes'], $actual['peak_memory_bytes']);
            if ($memoryChange > 0.15) {
                $warnings[] = sprintf('%s peak memory increased by %.1f%%.', $name, $memoryChange * 100);
            }
        }

        return $warnings;
    }

    private function relativeChange(float|int $baseline, float|int $current): float
    {
        if ($baseline <= 0) {
            throw new RuntimeException('Benchmark metrics must be positive.');
        }

        return ($current - $baseline) / $baseline;
    }

    private function validateDocument(array $document, string $label): array
    {
        if (!isset($document['php'], $document['scenarios'])
            || !is_string($document['php'])
            || !is_array($document['scenarios'])
            || $document['scenarios'] === []) {
            throw new RuntimeException('Invalid ' . $label . ' benchmark document.');
        }
        $iterations = $document['iterations'] ?? null;
        $samples = $document['samples'] ?? null;
        if (($iterations !== null && (!is_int($iterations) || $iterations <= 0))
            || ($samples !== null && (!is_int($samples) || $samples <= 0))) {
            throw new RuntimeException('Invalid ' . $label . ' benchmark configuration.');
        }
        $scenarios = [];
        foreach ($document['scenarios'] as $name => $scenario) {
            if (is_int($name) && is_array($scenario) && isset($scenario['name']) && is_string($scenario['name'])) {
                $name = $scenario['name'];
                unset($scenario['name']);
            }
            if ($iterations === null && is_array($scenario) && isset($scenario['iterations']) && is_int($scenario['iterations'])) {
                $iterations = $scenario['iterations'];
            }
            if ($samples === null && is_array($scenario) && isset($scenario['samples']) && is_int($scenario['samples'])) {
                $samples = $scenario['samples'];
            }
            unset($scenario['iterations'], $scenario['samples']);
            if (!is_string($name) || !is_array($scenario)
                || !isset($scenario['median_ms'], $scenario['peak_memory_bytes'], $scenario['output_sha256'])
                || (!is_float($scenario['median_ms']) && !is_int($scenario['median_ms']))
                || !is_int($scenario['peak_memory_bytes'])
                || !is_string($scenario['output_sha256'])
                || $scenario['median_ms'] <= 0
                || $scenario['peak_memory_bytes'] <= 0
                || preg_match('/^[a-f0-9]{64}$/', $scenario['output_sha256']) !== 1) {
                throw new RuntimeException('Invalid ' . $label . ' benchmark scenario: ' . $name . '.');
            }
            $scenarios[$name] = $scenario;
        }

        if ($scenarios === []) {
            throw new RuntimeException('Invalid ' . $label . ' benchmark document: no scenarios.');
        }
        if (!is_int($iterations) || $iterations <= 0 || !is_int($samples) || $samples <= 0) {
            throw new RuntimeException('Invalid ' . $label . ' benchmark configuration.');
        }

        $document['scenarios'] = $scenarios;
        $document['iterations'] = $iterations;
        $document['samples'] = $samples;

        return $document;
    }
}
