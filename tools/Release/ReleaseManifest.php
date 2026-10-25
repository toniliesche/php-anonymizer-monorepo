<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * @phpstan-type ReleaseManifestData array{schema_version: 1, release: array{version: string, branch: string, main_branch: string, source_sha: string}, packages: array<string, array{composer_name: string, repository: string, publication_branch: string, split_sha: string, archives: array{zip: array{file: string, sha256: string}, tar_gz: array{file: string, sha256: string}}}>}
 */
final readonly class ReleaseManifest implements JsonSerializable
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param array{schema_version: 1, release: array{version: string, branch: string, main_branch: string, source_sha: string}, packages: array<string, array{composer_name: string, repository: string, publication_branch: string, split_sha: string, archives: array{zip: array{file: string, sha256: string}, tar_gz: array{file: string, sha256: string}}}>} $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $train = new ReleaseTrain();
        if (!self::hasExactlyKeys($data, ['schema_version', 'release', 'packages']) || $data['schema_version'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Release manifest has an unsupported schema.');
        }

        $release = $data['release'];
        if (!is_array($release) || !self::hasExactlyKeys($release, ['version', 'branch', 'main_branch', 'source_sha'])) {
            throw new InvalidArgumentException('Release manifest release metadata is invalid.');
        }
        if (!is_string($release['version']) || !is_string($release['branch']) || !is_string($release['main_branch']) || !is_string($release['source_sha'])) {
            throw new InvalidArgumentException('Release manifest release metadata must contain strings.');
        }

        $version = $release['version'];
        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(?:0|[1-9]\d*)$/', $version, $matches) !== 1
            || $release['branch'] !== 'release/' . $matches[1] . '.' . $matches[2] . '.x'
            || $release['main_branch'] === ''
            || preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $release['source_sha']) !== 1
        ) {
            throw new InvalidArgumentException('Release manifest version, branch, main branch, or source SHA is invalid.');
        }

        $packages = $data['packages'];
        if (!is_array($packages) || !self::hasExactlyKeys($packages, $train->packages())) {
            throw new InvalidArgumentException('Release manifest must list every package exactly once.');
        }

        foreach ($train->packages() as $package) {
            self::validatePackage($packages[$package], $package, $version, $train);
        }

        /** @var ReleaseManifestData $data */
        return new self($data);
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Release manifest JSON must contain an object.');
        }

        return self::fromArray($data);
    }

    /**
     * @return ReleaseManifestData
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function version(): string
    {
        return $this->data['release']['version'];
    }

    /**
     * @return ReleaseManifestData
     */
    public function toArray(): array
    {
        return $this->data;
    }

    private static function validatePackage(mixed $entry, string $package, string $version, ReleaseTrain $train): void
    {
        if (!is_array($entry) || !self::hasExactlyKeys($entry, ['composer_name', 'repository', 'publication_branch', 'split_sha', 'archives'])) {
            throw new InvalidArgumentException('Release manifest package entry is invalid: ' . $package);
        }
        if ($entry['composer_name'] !== $train->composerNameFor($package)
            || $entry['repository'] !== $train->repositoryFor($package)
            || $entry['publication_branch'] !== $train->publicationBranchFor($version)
            || !is_string($entry['split_sha'])
            || preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $entry['split_sha']) !== 1
        ) {
            throw new InvalidArgumentException('Release manifest package identity is invalid: ' . $package);
        }

        $archives = $entry['archives'];
        if (!is_array($archives) || !self::hasExactlyKeys($archives, ['zip', 'tar_gz'])) {
            throw new InvalidArgumentException('Release manifest package archives are invalid: ' . $package);
        }
        foreach (['zip' => '.zip', 'tar_gz' => '.tar.gz'] as $archive => $extension) {
            $metadata = $archives[$archive];
            $expectedFile = $package . '-' . $version . $extension;
            if (!is_array($metadata)
                || !self::hasExactlyKeys($metadata, ['file', 'sha256'])
                || $metadata['file'] !== $expectedFile
                || !is_string($metadata['sha256'])
                || preg_match('/^[a-f0-9]{64}$/', $metadata['sha256']) !== 1
            ) {
                throw new InvalidArgumentException('Release manifest archive metadata is invalid: ' . $package . '/' . $archive);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactlyKeys(array $data, array $expected): bool
    {
        $keys = array_keys($data);

        return array_diff($keys, $expected) === [] && array_diff($expected, $keys) === [];
    }
}
