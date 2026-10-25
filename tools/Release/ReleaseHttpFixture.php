<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use RuntimeException;

final class ReleaseHttpFixture
{
    /** @var null|resource */
    private $process;

    private readonly string $directory;

    private readonly string $statePath;

    private readonly string $specificationPath;

    private readonly int $port;

    public function __construct(string $temporaryDirectory)
    {
        $this->directory = $temporaryDirectory . '/http-fixture';
        if (!mkdir($this->directory, 0o700)) {
            throw new RuntimeException('Could not create release HTTP fixture directory.');
        }
        $this->statePath = $this->directory . '/state.json';
        $this->specificationPath = $this->directory . '/routes.json';
        file_put_contents($this->statePath, "{}\n");
        file_put_contents($this->specificationPath, "{\"routes\":{}}\n");

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not reserve a local fixture port: ' . $errorMessage, $errorCode);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address) || preg_match('/:(\d+)$/', $address, $matches) !== 1) {
            throw new RuntimeException('Could not determine the local fixture port.');
        }
        $this->port = (int) $matches[1];
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function start(): void
    {
        if (is_resource($this->process)) {
            return;
        }
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/../fixtures/release-http-router.php'];
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->directory . '/server.log', 'a'],
            2 => ['file', $this->directory . '/server.log', 'a'],
        ];
        $this->process = proc_open($command, $descriptors, $pipes, null, [
            'RELEASE_FIXTURE_HTTP_SPEC' => $this->specificationPath,
            'RELEASE_FIXTURE_HTTP_STATE' => $this->statePath,
        ]);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Could not start the local release HTTP fixture.');
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('Local release HTTP fixture did not start.');
    }

    /**
     * @param list<array{status: int, body: array<mixed>|string, headers?: array<string, string>}> $responses
     */
    public function setResponses(string $method, string $path, array $responses): void
    {
        if (preg_match('/^[A-Z]+$/', $method) !== 1 || !str_starts_with($path, '/') || $responses === []) {
            throw new RuntimeException('HTTP fixture route is invalid.');
        }
        $specification = json_decode((string) file_get_contents($this->specificationPath), true, 512, JSON_THROW_ON_ERROR);
        $specification['routes'][$method . ' ' . $path] = $responses;
        file_put_contents($this->specificationPath, json_encode($specification, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /**
     * @return array{requests: array<string, int>, observed: list<array{method: string, path: string, authorization_present: bool}>}
     */
    public function state(): array
    {
        $state = json_decode((string) file_get_contents($this->statePath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state)) {
            throw new RuntimeException('HTTP fixture state is invalid.');
        }

        return [
            'requests' => $state['requests'] ?? [],
            'observed' => $state['observed'] ?? [],
        ];
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
    }
}
