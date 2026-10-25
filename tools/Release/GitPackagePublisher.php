<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class GitPackagePublisher
{
    /**
     * @param array<string, string> $repositoryOverrides use local repository URLs in isolated tests
     */
    public function __construct(
        private ReleaseTrain $train = new ReleaseTrain(),
        private array $repositoryOverrides = [],
    ) {
    }

    public function preflight(ReleaseSplitPlan $splitPlan, string $root, ?string $token = null): ReleasePublicationPlan
    {
        $root = realpath($root) ?: throw new InvalidArgumentException('Release repository root does not exist.');
        $this->validateSplitPlan($splitPlan, $root);

        return $this->withCredentials($token, function () use ($splitPlan, $root): ReleasePublicationPlan {
            $version = $splitPlan->version;
            $branch = $this->train->publicationBranchFor($version);
            $analysisRepository = sys_get_temp_dir() . '/php-anonymizer-publication-check-' . bin2hex(random_bytes(8));
            $this->runGit($root, ['init', '--bare', '--quiet', $analysisRepository]);

            try {
                $packages = [];
                foreach ($this->train->packages() as $package) {
                    $entry = $splitPlan->packages[$package];
                    $repository = $this->repositoryFor($package, $entry['repository']);
                    $refs = $this->remoteRefs($root, $repository);
                    $branchRef = 'refs/heads/' . $branch;
                    $tagRef = 'refs/tags/' . $version;
                    $remoteBranchSha = $refs[$branchRef] ?? null;
                    $remoteTagSha = $refs[$tagRef] ?? null;
                    $remoteTagTarget = $refs[$tagRef . '^{}'] ?? $remoteTagSha;

                    if ($remoteTagSha !== null && $remoteTagTarget !== $entry['split_sha']) {
                        throw new RuntimeException(sprintf('Release tag %s in %s points to a different package split.', $version, $package));
                    }

                    $pushBranch = $remoteBranchSha !== $entry['split_sha'];
                    if ($remoteBranchSha !== null && $pushBranch) {
                        $remoteRef = 'refs/remotes/preflight/' . $package;
                        $this->runGit($analysisRepository, ['fetch', '--quiet', '--no-tags', $repository, '+' . $branchRef . ':' . $remoteRef]);
                        $this->runGit($analysisRepository, ['fetch', '--quiet', '--no-tags', $root, $entry['split_sha']]);
                        $ancestry = $this->executeGit($analysisRepository, ['merge-base', '--is-ancestor', $remoteRef, $entry['split_sha']]);
                        if ($ancestry['status'] === 1) {
                            throw new RuntimeException(sprintf('Publication branch %s in %s is not an ancestor of the planned split; refusing a branch regression.', $branch, $package));
                        }
                        if ($ancestry['status'] !== 0) {
                            throw new RuntimeException('Could not verify publication branch ancestry: ' . $ancestry['stderr']);
                        }
                    }

                    $packages[$package] = [
                        'repository' => $repository,
                        'split_sha' => $entry['split_sha'],
                        'push_branch' => $pushBranch,
                        'push_tag' => $remoteTagSha === null,
                    ];
                }

                return new ReleasePublicationPlan($splitPlan, $branch, $packages);
            } finally {
                $this->removeDirectory($analysisRepository);
            }
        });
    }

    /**
     * Publish a previously preflighted plan. Each package's branch and tag are
     * pushed in one atomic operation; repeating the preflight makes retries safe.
     *
     * @return array<string, 'already_published'|'published'>
     */
    public function publish(ReleasePublicationPlan $publicationPlan, string $root, ?string $token = null): array
    {
        $root = realpath($root) ?: throw new InvalidArgumentException('Release repository root does not exist.');
        $token ??= getenv('PACKAGE_PUBLISH_TOKEN') ?: null;
        $token = $token === '' ? null : $token;
        $publicationPlan = $this->preflight($publicationPlan->splitPlan, $root, $token);
        foreach ($publicationPlan->packages as $entry) {
            if (($entry['push_branch'] || $entry['push_tag']) && $this->isGithubRepository($entry['repository']) && $token === null) {
                throw new InvalidArgumentException('PACKAGE_PUBLISH_TOKEN is required to publish package repositories.');
            }
        }

        return $this->withCredentials($token, function () use ($publicationPlan, $root): array {
            $publishRepository = sys_get_temp_dir() . '/php-anonymizer-package-publish-' . bin2hex(random_bytes(8));
            $this->runGit($root, ['init', '--bare', '--quiet', $publishRepository]);

            try {
                $temporaryTags = [];
                foreach ($this->train->packages() as $package) {
                    $entry = $publicationPlan->packages[$package] ?? throw new InvalidArgumentException('Publication plan is missing package: ' . $package);
                    if (!$entry['push_branch'] && !$entry['push_tag']) {
                        continue;
                    }
                    $this->runGit($publishRepository, ['fetch', '--quiet', '--no-tags', $root, $entry['split_sha']]);
                    if ($entry['push_tag']) {
                        $temporaryTag = 'release/' . $package . '/' . $publicationPlan->splitPlan->version;
                        $this->runGit($publishRepository, [
                            '-c', 'user.name=PHP Anonymizer Release',
                            '-c', 'user.email=dev@toniliesche.de',
                            '-c', 'tag.gpgSign=false',
                            'tag', '-a', $temporaryTag, $entry['split_sha'], '-m', 'Release ' . $publicationPlan->splitPlan->version,
                        ]);
                        $temporaryTags[$package] = $temporaryTag;
                    }
                }

                $results = [];
                foreach ($this->train->packages() as $package) {
                    $entry = $publicationPlan->packages[$package];
                    if (!$entry['push_branch'] && !$entry['push_tag']) {
                        $results[$package] = 'already_published';

                        continue;
                    }

                    $refs = [];
                    if ($entry['push_branch']) {
                        $refs[] = $entry['split_sha'] . ':refs/heads/' . $publicationPlan->branch;
                    }
                    if ($entry['push_tag']) {
                        $refs[] = $temporaryTags[$package] . ':refs/tags/' . $publicationPlan->splitPlan->version;
                    }
                    $this->runGit($publishRepository, [
                        '-c', 'credential.helper=', 'push', '--atomic', '--porcelain', $entry['repository'], ...$refs,
                    ]);
                    $results[$package] = 'published';
                }

                return $results;
            } finally {
                $this->removeDirectory($publishRepository);
            }
        });
    }

    private function validateSplitPlan(ReleaseSplitPlan $plan, string $root): void
    {
        $matches = [];
        if (array_keys($plan->packages) !== $this->train->packages()
            || preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.\d+$/', $plan->version, $matches) !== 1
            || $plan->branch !== 'release/' . $matches[1] . '.' . $matches[2] . '.x'
        ) {
            throw new InvalidArgumentException('Release split plan does not match the release train.');
        }

        $publicationBranch = $this->train->publicationBranchFor($plan->version);
        foreach ($this->train->packages() as $package) {
            $entry = $plan->packages[$package];
            if ($entry['composer_name'] !== $this->train->composerNameFor($package)
                || $entry['repository'] !== $this->train->repositoryFor($package)
                || $entry['publication_branch'] !== $publicationBranch
                || preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $entry['split_sha']) !== 1
            ) {
                throw new InvalidArgumentException('Release split package identity is invalid: ' . $package);
            }
            $this->runGit($root, ['cat-file', '-e', $entry['split_sha'] . '^{commit}']);
            $manifest = json_decode($this->runGit($root, ['show', $entry['split_sha'] . ':composer.json']), true);
            if (!is_array($manifest) || ($manifest['name'] ?? null) !== $entry['composer_name']) {
                throw new InvalidArgumentException('Release split does not contain the declared Composer root: ' . $package);
            }
        }
    }

    private function repositoryFor(string $package, string $configuredRepository): string
    {
        $repository = $this->repositoryOverrides[$package] ?? $configuredRepository;
        if ($repository === '' || preg_match('#^(?:https://github\.com/|/)#', $repository) !== 1) {
            throw new InvalidArgumentException('Package destination must be a GitHub HTTPS URL or an absolute local test repository.');
        }

        return $repository;
    }

    private function isGithubRepository(string $repository): bool
    {
        return parse_url($repository, PHP_URL_HOST) === 'github.com';
    }

    /**
     * @return array<string, string>
     */
    private function remoteRefs(string $root, string $repository): array
    {
        $output = $this->runGit($root, ['ls-remote', '--heads', '--tags', $repository]);
        $refs = [];
        if ($output === '') {
            return $refs;
        }
        foreach (preg_split('/\R+/', $output) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{40}|[a-f0-9]{64})\s+(refs\/(?:heads|tags)\/\S+)$/', $line, $matches) !== 1) {
                throw new RuntimeException('Remote returned an invalid Git ref listing.');
            }
            $refs[$matches[2]] = $matches[1];
        }

        return $refs;
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(string $directory, array $arguments): string
    {
        $result = $this->executeGit($directory, $arguments);
        if ($result['status'] !== 0) {
            throw new RuntimeException('Git publication command failed: ' . $result['stderr']);
        }

        return trim($result['stdout']);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function executeGit(string $directory, array $arguments): array
    {
        $process = proc_open(['git', '-C', $directory, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a Git publication command.');
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function withCredentials(?string $token, callable $operation): mixed
    {
        $askpass = null;
        $previous = [];
        if ($token !== null && $token !== '') {
            $askpass = tempnam(sys_get_temp_dir(), 'php-anonymizer-askpass-') ?: throw new RuntimeException('Could not create a Git credential helper.');
            file_put_contents($askpass, <<<'SH_WRAP'
                #!/bin/sh
                case "$1" in
                    *Username*) printf '%s\n' x-access-token ;;
                    *Password*) printf '%s\n' "$PACKAGE_PUBLISH_TOKEN" ;;
                    *) exit 1 ;;
                esac
                SH_WRAP);
            if (!chmod($askpass, 0o700)) {
                unlink($askpass);

                throw new RuntimeException('Could not secure the Git credential helper.');
            }
            foreach ([
                'PACKAGE_PUBLISH_TOKEN' => $token,
                'GIT_ASKPASS' => $askpass,
                'GIT_TERMINAL_PROMPT' => '0',
            ] as $name => $value) {
                $previous[$name] = getenv($name);
                putenv($name . '=' . $value);
            }
        }

        try {
            return $operation();
        } finally {
            foreach ($previous as $name => $value) {
                $value === false ? putenv($name) : putenv($name . '=' . $value);
            }
            if (is_string($askpass) && is_file($askpass)) {
                unlink($askpass);
            }
        }
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
}
