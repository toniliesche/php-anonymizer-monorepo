<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use InvalidArgumentException;
use RuntimeException;

final readonly class ReleaseSplitPlanner
{
    public function __construct(
        private ReleaseTrain $train = new ReleaseTrain(),
        private ReleaseReadiness $readiness = new ReleaseReadiness(),
    ) {
    }

    public function plan(string $version, string $releaseBranch, string $mainBranch, string $sourceSha, string $root): ReleaseSplitPlan
    {
        $publicationBranch = $this->train->publicationBranchFor($version);
        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.\d+$/', $version, $matches) !== 1
            || $releaseBranch !== 'release/' . $matches[1] . '.' . $matches[2] . '.x'
        ) {
            throw new InvalidArgumentException('Release branch does not match the stable release version.');
        }
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $sourceSha) !== 1) {
            throw new InvalidArgumentException('Release source must be a full lowercase Git commit SHA.');
        }

        $root = realpath($root) ?: throw new InvalidArgumentException('Release repository root does not exist.');
        $mainRef = 'refs/heads/' . $mainBranch;
        $this->runGit($root, ['check-ref-format', '--branch', $mainBranch]);
        $resolvedSource = $this->runGit($root, ['rev-parse', '--verify', $sourceSha . '^{commit}']);
        if ($resolvedSource !== $sourceSha) {
            throw new InvalidArgumentException('Release source SHA does not resolve to the requested commit.');
        }
        $this->runGit($root, ['rev-parse', '--verify', $mainRef . '^{commit}']);
        $ancestry = $this->executeGit($root, ['merge-base', '--is-ancestor', $sourceSha, $mainRef]);
        if ($ancestry['status'] === 1) {
            throw new InvalidArgumentException('Release source commit is not reachable from ' . $mainBranch . '.');
        }
        if ($ancestry['status'] !== 0) {
            throw new RuntimeException('Could not verify release source ancestry: ' . $ancestry['stderr']);
        }

        $snapshot = sys_get_temp_dir() . '/php-anonymizer-split-' . bin2hex(random_bytes(8));
        $worktreeAdded = false;

        try {
            $this->runGit($root, ['worktree', 'add', '--quiet', '--detach', $snapshot, $sourceSha]);
            $worktreeAdded = true;
            $this->readiness->assert($version, $snapshot);

            $packages = [];
            foreach ($this->train->packages() as $package) {
                $splitSha = $this->runGit($root, ['subtree', 'split', '--quiet', '--prefix=packages/' . $package, $sourceSha]);
                if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $splitSha) !== 1) {
                    throw new RuntimeException('Git subtree returned an invalid split SHA for ' . $package . '.');
                }
                $splitManifest = json_decode($this->runGit($root, ['show', $splitSha . ':composer.json']), true);
                if (!is_array($splitManifest) || ($splitManifest['name'] ?? null) !== $this->train->composerNameFor($package)) {
                    throw new RuntimeException('Package split has an invalid Composer root: ' . $package);
                }

                $packages[$package] = [
                    'composer_name' => $this->train->composerNameFor($package),
                    'repository' => $this->train->repositoryFor($package),
                    'publication_branch' => $publicationBranch,
                    'split_sha' => $splitSha,
                ];
            }

            return new ReleaseSplitPlan($version, $releaseBranch, $mainBranch, $sourceSha, $packages);
        } finally {
            if ($worktreeAdded) {
                $this->runGit($root, ['worktree', 'remove', '--force', $snapshot]);
            }
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(string $root, array $arguments): string
    {
        $result = $this->executeGit($root, $arguments);
        if ($result['status'] !== 0) {
            if (($arguments[0] ?? null) === 'subtree' && str_contains($result['stderr'], 'not a git command')) {
                throw new RuntimeException('Git subtree is required for package split planning; install the git-subtree package on the runner.');
            }

            throw new RuntimeException('Git command failed: ' . $result['stderr']);
        }

        return trim($result['stdout']);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function executeGit(string $root, array $arguments): array
    {
        $process = proc_open(['git', '-C', $root, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a Git release planning command.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }
}
