<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class PackagistReleaseVerifier
{
    /**
     * @param null|callable(int): void $wait
     */
    public function __construct(
        private string $baseUrl = 'https://packagist.org',
        private mixed $wait = null,
        private int $pollInterval = 30,
        private int $timeout = 600,
    ) {
        if ($pollInterval < 1 || $timeout < 1) {
            throw new RuntimeException('Packagist polling interval and timeout must be positive.');
        }
    }

    public function triggerUpdates(ReleaseManifest $manifest, string $username, string $apiToken): void
    {
        if ($username === '' || $apiToken === '') {
            throw new RuntimeException('PACKAGIST_USERNAME and PACKAGIST_API_TOKEN are required.');
        }

        $packages = $manifest->toArray()['packages'];
        foreach (array_values($packages) as $index => $entry) {
            $this->progress(sprintf('Packagist update %d/%d: requesting %s.', $index + 1, count($packages), $entry['composer_name']));
            $this->request('POST', '/api/update-package', 'Bearer ' . $username . ':' . $apiToken, ['repository' => $entry['repository']]);
            $this->progress('Packagist accepted the update request for ' . $entry['composer_name'] . '.');
        }
    }

    public function verify(ReleaseManifest $manifest): void
    {
        $deadline = time() + $this->timeout;
        $attempt = 0;
        do {
            $attempt++;
            $pending = [];
            $packages = $manifest->toArray()['packages'];
            $this->progress(sprintf('Packagist metadata check %d for %d packages.', $attempt, count($packages)));
            foreach ($packages as $package => $entry) {
                try {
                    $metadata = $this->request('GET', '/p2/' . $entry['composer_name'] . '.json');
                    if (!$this->containsPublishedVersion($metadata, $manifest->version(), $entry['split_sha'])) {
                        $pending[] = $package;
                        $this->progress('Packagist has not published the expected version/reference for ' . $entry['composer_name'] . ' yet.');
                    } else {
                        $this->progress('Verified ' . $entry['composer_name'] . ' ' . $manifest->version() . '.');
                    }
                } catch (RuntimeException) {
                    $pending[] = $package;
                    $this->progress('Packagist metadata is not available yet for ' . $entry['composer_name'] . '.');
                }
            }
            if ($pending === []) {
                $this->progress('Packagist metadata verification passed for all packages.');

                return;
            }
            if (time() >= $deadline) {
                throw new RuntimeException('Packagist did not publish the expected versions and source references before timeout: ' . implode(', ', $pending));
            }
            $wait = min($this->pollInterval, max(1, $deadline - time()));
            $this->progress(sprintf('Waiting %d seconds before retrying; pending packages: %s.', $wait, implode(', ', $pending)));
            if (is_callable($this->wait)) {
                ($this->wait)($wait);
            } else {
                sleep($wait);
            }
        } while (true);
    }

    public function installCleanConsumer(ReleaseManifest $manifest): void
    {
        $this->progress(sprintf('Creating a clean consumer for %d packages at version %s.', count($manifest->toArray()['packages']), $manifest->version()));
        $directory = sys_get_temp_dir() . '/php-anonymizer-packagist-consumer-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0o700)) {
            throw new RuntimeException('Could not create clean Packagist consumer directory.');
        }

        try {
            $require = [];
            foreach ($manifest->toArray()['packages'] as $entry) {
                $require[$entry['composer_name']] = $manifest->version();
            }
            file_put_contents($directory . '/composer.json', json_encode([
                'name' => 'php-anonymizer/release-consumer',
                'type' => 'project',
                'require' => $require,
                'minimum-stability' => 'stable',
                'prefer-stable' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $process = proc_open(['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts'], [
                0 => ['file', '/dev/null', 'r'],
                1 => STDOUT,
                2 => STDERR,
            ], $pipes, $directory);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start clean Composer consumer installation.');
            }
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Clean Packagist consumer installation failed; see the preceding Composer output.');
            }
            $this->progress('Clean Composer consumer installation passed.');
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /**
     * @param null|array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?string $authorization = null, ?array $payload = null): array
    {
        $headers = ['Accept: application/json'];
        if ($authorization !== null) {
            $headers[] = 'Authorization: ' . $authorization;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 15,
        ]]);
        $body = @file_get_contents(rtrim($this->baseUrl, '/') . $path, false, $context);
        $responseHeaders = http_get_last_response_headers() ?: [];
        $statusLine = $responseHeaders[0] ?? '';
        if (!is_string($body) || preg_match('/\s(\d{3})\s/', $statusLine, $matches) !== 1 || (int) $matches[1] < 200 || (int) $matches[1] >= 300) {
            $status = preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1 ? ' (HTTP ' . $matches[1] . ')' : '';
            $responseBody = is_string($body) ? trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '') : '';
            $responseBody = $responseBody === '' ? '' : ': ' . substr($responseBody, 0, 500);

            throw new RuntimeException('Packagist ' . $method . ' request failed for ' . $path . $status . $responseBody . '.');
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function containsPublishedVersion(array $metadata, string $version, string $sourceSha): bool
    {
        $versions = $metadata['packages'] ?? null;
        if (!is_array($versions)) {
            return false;
        }
        foreach ($versions as $entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (is_array($entry)
                    && ($entry['version'] ?? null) === $version
                    && ($entry['source']['reference'] ?? null) === $sourceSha
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }

    private function progress(string $message): void
    {
        fwrite(STDOUT, '[release] ' . $message . PHP_EOL);
        fflush(STDOUT);
    }
}
