<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use RuntimeException;

final readonly class GitHubReleaseClient
{
    public function __construct(
        private string $token,
        private string $repository = 'toniliesche/php-anonymizer-monorepo',
        private string $apiBase = 'https://api.github.com',
    ) {
        if ($token === '') {
            throw new RuntimeException('GITHUB_TOKEN is required for release publication.');
        }
    }

    /**
     * @return null|array<string, mixed>
     */
    public function releaseForTag(string $tag): ?array
    {
        try {
            return $this->request('GET', '/repos/' . $this->repository . '/releases/tags/' . rawurlencode($tag));
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'HTTP 404')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createDraft(string $tag, string $sourceSha): array
    {
        return $this->request('POST', '/repos/' . $this->repository . '/releases', [
            'tag_name' => $tag,
            'target_commitish' => $sourceSha,
            'name' => $tag,
            'body' => 'PHP Anonymizer ' . $tag . ' release.',
            'draft' => true,
            'prerelease' => false,
        ]);
    }

    public function uploadAsset(array $release, string $file): void
    {
        $name = basename($file);
        foreach ($release['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? null) === $name) {
                throw new RuntimeException('GitHub release already contains asset ' . $name . '; refusing to replace release content.');
            }
        }
        $uploadUrl = $release['upload_url'] ?? null;
        if (!is_string($uploadUrl)) {
            throw new RuntimeException('GitHub release response did not include an asset upload URL.');
        }
        $uploadUrl = preg_replace('/\{.*$/', '', $uploadUrl);
        $url = $uploadUrl . '?name=' . rawurlencode($name);
        $mime = match (pathinfo($name, PATHINFO_EXTENSION)) {
            'zip' => 'application/zip',
            'gz' => 'application/gzip',
            default => 'application/json',
        };
        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            throw new RuntimeException('Could not read GitHub release asset ' . $name . '.');
        }
        $this->requestUrl('POST', $url, $contents, $mime);
    }

    public function downloadAsset(array $release, string $name, string $destination): void
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? null) === $name && is_string($asset['browser_download_url'] ?? null)) {
                $content = $this->requestUrl('GET', $asset['browser_download_url']);
                if (file_put_contents($destination, $content) === false) {
                    throw new RuntimeException('Could not persist GitHub release asset ' . $name . '.');
                }

                return;
            }
        }

        throw new RuntimeException('Saved release is missing required asset ' . $name . '.');
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(array $release): array
    {
        $id = $release['id'] ?? null;
        if (!is_int($id)) {
            throw new RuntimeException('GitHub release has no valid id.');
        }

        return $this->request('PATCH', '/repos/' . $this->repository . '/releases/' . $id, ['draft' => false]);
    }

    public function openOrReuseSynchronizationPullRequest(): void
    {
        $path = '/repos/' . $this->repository . '/pulls?state=open&base=develop&head=toniliesche:main';
        $pulls = $this->request('GET', $path);
        if ($pulls !== []) {
            return;
        }
        $this->request('POST', '/repos/' . $this->repository . '/pulls', [
            'title' => 'Merge main into develop',
            'head' => 'main',
            'base' => 'develop',
            'body' => 'Synchronize the published release back to the development branch.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->requestUrl($method, rtrim($this->apiBase, '/') . $path, $body, 'application/json');
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GitHub API returned an invalid JSON response.');
        }

        return $decoded;
    }

    private function requestUrl(string $method, string $url, ?string $body = null, string $contentType = 'application/json', bool $authorize = true): string
    {
        $headers = "Accept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\nContent-Type: " . $contentType . "\r\nUser-Agent: php-anonymizer-release\r\n";
        if ($authorize) {
            $headers .= 'Authorization: Bearer ' . $this->token . "\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 30,
            'follow_location' => 0,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = http_get_last_response_headers() ?: [];
        $statusLine = $responseHeaders[0] ?? '';
        if (preg_match('/\s(30[1278])\s/', $statusLine) === 1) {
            $location = null;
            foreach ($responseHeaders as $header) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));

                    break;
                }
            }
            $redirectHost = is_string($location) ? parse_url($location, PHP_URL_HOST) : null;
            if (!is_string($location) || parse_url($location, PHP_URL_SCHEME) !== 'https' || !is_string($redirectHost)
                || !in_array($redirectHost, ['github.com', 'api.github.com', 'uploads.github.com'], true)
                    && !str_ends_with($redirectHost, '.githubusercontent.com')
            ) {
                throw new RuntimeException('GitHub API returned an unsupported asset redirect.');
            }

            return $this->requestUrl($method === 'POST' ? 'GET' : $method, $location, null, $contentType, false);
        }
        if (!is_string($response) || preg_match('/\s(\d{3})\s/', $statusLine, $matches) !== 1 || (int) $matches[1] < 200 || (int) $matches[1] >= 300) {
            $status = preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1 ? 'HTTP ' . $matches[1] : 'network error';

            throw new RuntimeException('GitHub API request failed (' . $status . ').');
        }

        return $response;
    }
}
