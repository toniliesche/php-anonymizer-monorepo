<?php

declare(strict_types=1);

namespace PhpAnonymizer\CI;

use RuntimeException;

final class GateRunner
{
    /**
     * @param list<string> $command @return array{exit_code: int, directory: string, metadata: array<string, mixed>}
     */
    public function run(string $name, array $command, string $root): array
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $name) !== 1 || $command === []) {
            throw new RuntimeException('Gate name or command is invalid.');
        }
        $root = realpath($root) ?: throw new RuntimeException('Gate working directory does not exist.');
        $runId = getenv('GITHUB_RUN_ID') ?: getenv('GITEA_RUN_ID') ?: 'local-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        $runId = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $runId) ?: 'local';
        $attempt = getenv('GITHUB_RUN_ATTEMPT') ?: getenv('GITEA_RUN_ATTEMPT') ?: '1';
        $directoryId = $runId . '-attempt-' . preg_replace('/[^A-Za-z0-9_.-]+/', '-', $attempt);
        $directory = getenv('CI_DIAGNOSTICS_DIR') ?: $root . '/var/ci-diagnostics/' . $directoryId;
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create gate diagnostics directory.');
        }
        $logPath = $directory . '/' . $name . '.log';
        $log = fopen($logPath, 'wb');
        if (!is_resource($log)) {
            throw new RuntimeException('Could not create gate log.');
        }
        $started = gmdate(DATE_ATOM);
        $startedEpoch = time();
        $coverageSource = $root . '/var/coverage.xml';
        $coverageBefore = is_file($coverageSource) ? hash_file('sha256', $coverageSource) : null;
        $coverageMtimeBefore = is_file($coverageSource) ? (filemtime($coverageSource) ?: 0) : 0;
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            fclose($log);

            throw new RuntimeException('Could not start Composer gate.');
        }
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        $lastStatus = ['running' => true, 'exitcode' => -1];
        $observedExitCode = null;
        while (true) {
            $lastStatus = proc_get_status($process);
            if (!$lastStatus['running'] && $lastStatus['exitcode'] >= 0) {
                $observedExitCode = $lastStatus['exitcode'];
            }
            $read = array_values(array_filter($pipes, static fn ($pipe): bool => is_resource($pipe) && !feof($pipe)));
            if ($read === []) {
                break;
            }
            $write = null;
            $except = null;
            $ready = stream_select($read, $write, $except, 1);
            if ($ready === false || $ready === 0) {
                if (!$lastStatus['running']) {
                    foreach ($pipes as $pipe) {
                        if (is_resource($pipe) && !feof($pipe)) {
                            $read[] = $pipe;
                        }
                    }
                    if (count($read) === 0) {
                        break;
                    }
                }

                continue;
            }
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 8192);
                if (!is_string($chunk) || $chunk === '') {
                    continue;
                }
                $chunk = $this->redact($chunk);
                fwrite($log, $chunk);
                fflush($log);
                fwrite($pipe === $pipes[1] ? STDOUT : STDERR, $chunk);
            }
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $closedCode = proc_close($process);
        $exitCode = $closedCode >= 0 ? $closedCode : ($observedExitCode ?? (int) $lastStatus['exitcode']);
        fclose($log);
        $this->collectCoverage($root, $directory, $startedEpoch, $coverageBefore, $coverageMtimeBefore);
        $mutationLines = $this->mutationSummary((string) file_get_contents($logPath));
        file_put_contents($directory . '/mutation-summary.txt', $mutationLines === [] ? "No mutation summary was emitted by this gate.\n" : implode("\n", $mutationLines) . "\n");
        foreach (glob($root . '/var/infection*.log') ?: [] as $infectionLog) {
            if (is_file($infectionLog) && (filemtime($infectionLog) ?: 0) >= $startedEpoch) {
                copy($infectionLog, $directory . '/' . basename($infectionLog));
            }
        }
        $metadata = [
            'event' => getenv('GITHUB_EVENT_NAME') ?: getenv('GITEA_EVENT_NAME') ?: getenv('CI_EVENT_NAME') ?: 'local',
            'revision' => getenv('GITHUB_SHA') ?: getenv('GITEA_SHA') ?: getenv('CI_EVENT_SHA') ?: null,
            'run_id' => $runId,
            'run_attempt' => $attempt,
            'gate' => $name,
            'command' => array_map($this->redact(...), $command),
            'started_at' => $started,
            'finished_at' => gmdate(DATE_ATOM),
            'exit_code' => $exitCode,
            'outcome' => $exitCode === 0 ? 'success' : 'failure',
            'coverage_file' => is_file($directory . '/coverage.xml') ? 'coverage.xml' : null,
            'checkout' => is_file($directory . '/checkout.json') ? json_decode((string) file_get_contents($directory . '/checkout.json'), true) : null,
        ];
        file_put_contents($directory . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        return ['exit_code' => $exitCode, 'directory' => $directory, 'metadata' => $metadata];
    }

    private function collectCoverage(string $root, string $directory, int $startedEpoch, string|false|null $coverageBefore, int $coverageMtimeBefore): void
    {
        $coverage = $root . '/var/coverage.xml';
        if (!is_file($coverage)) {
            return;
        }
        $coverageAfter = hash_file('sha256', $coverage);
        $mtime = filemtime($coverage) ?: 0;
        if (($coverageAfter !== $coverageBefore || $mtime > $coverageMtimeBefore) && $mtime >= $startedEpoch && !copy($coverage, $directory . '/coverage.xml')) {
            throw new RuntimeException('Could not preserve the coverage report.');
        }
    }

    /**
     * @return list<string>
     */
    private function mutationSummary(string $log): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $log) ?: [] as $line) {
            if (preg_match('/mutation|\bMSI\b|covered code|escaped|killed|timed out/i', $line) === 1) {
                $lines[] = trim($line);
            }
        }

        return array_slice($lines, -300);
    }

    private function redact(string $content): string
    {
        foreach (['GITHUB_TOKEN', 'GH_TOKEN', 'GITEA_TOKEN', 'PACKAGE_PUBLISH_TOKEN', 'PACKAGIST_API_TOKEN', 'COMPOSER_AUTH', 'CI_JOB_TOKEN', 'REGISTRY_PASSWORD'] as $name) {
            $secret = getenv($name);
            if (is_string($secret) && $secret !== '') {
                $content = str_replace($secret, '[REDACTED]', $content);
            }
        }

        return $content;
    }
}
