<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

/**
 * @phpstan-type PublicationPackageData array{repository: string, split_sha: string, push_branch: bool, push_tag: bool}
 * @phpstan-type ReleasePublicationPlanData array{version: string, branch: string, packages: array<string, PublicationPackageData>}
 */
final readonly class ReleasePublicationPlan
{
    /**
     * @param array<string, array{repository: string, split_sha: string, push_branch: bool, push_tag: bool}> $packages
     */
    public function __construct(
        public ReleaseSplitPlan $splitPlan,
        public string $branch,
        public array $packages,
    ) {
    }

    public function version(): string
    {
        return $this->splitPlan->version;
    }

    /**
     * @return ReleasePublicationPlanData
     */
    public function toArray(): array
    {
        return ['version' => $this->splitPlan->version, 'branch' => $this->branch, 'packages' => $this->packages];
    }
}
