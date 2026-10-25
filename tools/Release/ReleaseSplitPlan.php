<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use JsonSerializable;

/**
 * @phpstan-type SplitPackageData array{composer_name: string, repository: string, publication_branch: string, split_sha: string}
 * @phpstan-type ReleaseSplitPlanData array{schema_version: 1, release: array{version: string, branch: string, main_branch: string, source_sha: string}, packages: array<string, SplitPackageData>}
 */
final readonly class ReleaseSplitPlan implements JsonSerializable
{
    /**
     * @param array<string, array{composer_name: string, repository: string, publication_branch: string, split_sha: string}> $packages
     */
    public function __construct(
        public string $version,
        public string $branch,
        public string $mainBranch,
        public string $sourceSha,
        public array $packages,
    ) {
    }

    /**
     * @return ReleaseSplitPlanData
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return ReleaseSplitPlanData
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'release' => [
                'version' => $this->version,
                'branch' => $this->branch,
                'main_branch' => $this->mainBranch,
                'source_sha' => $this->sourceSha,
            ],
            'packages' => $this->packages,
        ];
    }
}
