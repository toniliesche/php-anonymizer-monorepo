<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use InvalidArgumentException;
use RuntimeException;

final readonly class ReleaseOrchestrator
{
    public function __construct(
        private ReleaseReadiness $readiness = new ReleaseReadiness(),
        private ReleaseSplitPlanner $splitPlanner = new ReleaseSplitPlanner(),
        private GitPackagePublisher $publisher = new GitPackagePublisher(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function dryRun(string $version, string $branch, string $mainBranch, string $sourceSha, string $root): array
    {
        $this->progress(sprintf('Starting dry-run for release %s at %s.', $version, $sourceSha));
        $this->progress('Checking release readiness.');
        $this->readiness->assert($version, $root);
        $this->progress('Planning package splits and preflighting destinations.');
        $splitPlan = $this->splitPlanner->plan($version, $branch, $mainBranch, $sourceSha, $root);
        $this->publisher->preflight($splitPlan, $root);
        $this->progress('Dry-run checks passed.');

        return [
            'validate release readiness for ' . $version . ' at ' . $sourceSha,
            'build fourteen package archives and checksums from the fixed source revision',
            'persist the release manifest and artifacts to a draft GitHub release',
            'create or verify the monorepo tag ' . $version,
            'publish seven package branches and annotated tags after all destinations pass preflight',
            'trigger Packagist updates and verify seven source references',
            'install all seven exact versions in a clean Composer consumer',
            'publish the GitHub release and open or reuse the main-to-develop pull request',
        ];
    }

    public function run(string $version, string $branch, string $mainBranch, string $sourceSha, string $root, string $githubToken): void
    {
        $root = realpath($root) ?: throw new InvalidArgumentException('Release repository root does not exist.');
        if ($githubToken === '') {
            throw new RuntimeException('GITHUB_TOKEN is required for release publication.');
        }
        foreach (['PACKAGE_PUBLISH_TOKEN', 'PACKAGIST_USERNAME', 'PACKAGIST_API_TOKEN'] as $credential) {
            if ((getenv($credential) ?: '') === '') {
                throw new RuntimeException($credential . ' is required for release publication.');
            }
        }
        $this->progress(sprintf('Starting release %s from %s.', $version, $sourceSha));
        $this->progress('Checking release readiness and resolving the exact source commit.');
        $this->readiness->assert($version, $root);
        $sourceSha = $this->resolveSource($sourceSha, $root);
        $this->progress('Release readiness passed; source commit is ' . $sourceSha . '.');
        $client = new GitHubReleaseClient($githubToken);
        $this->progress('Checking GitHub for an existing draft release.');
        $release = $client->releaseForTag($version);
        $artifactDirectory = $root . '/var/artifacts/' . $version;
        $savedManifestPath = $artifactDirectory . '/release-manifest.json';
        $hasSavedManifest = $release !== null && $this->hasAsset($release, 'release-manifest.json');
        if ($release === null || !$hasSavedManifest) {
            $this->progress($release === null ? 'No draft release found; preparing a new release.' : 'Draft release found without a saved manifest; rebuilding its release assets.');
            if ($release !== null) {
                $artifactDirectory = sys_get_temp_dir() . '/php-anonymizer-release-resume-' . bin2hex(random_bytes(8));
            }
            $this->progress('Planning package splits and checking all package destinations.');
            $splitPlan = $this->splitPlanner->plan($version, $branch, $mainBranch, $sourceSha, $root);
            $publicationPlan = $this->publisher->preflight($splitPlan, $root);
            $manifest = $this->buildManifest($splitPlan, $root, $artifactDirectory);
            $this->progress('Checking or creating the monorepo release tag ' . $version . '.');
            $this->ensureMonorepoTag($version, $sourceSha, $root);
            if ($release === null) {
                $this->progress('Creating the GitHub draft release.');
                $release = $client->createDraft($version, $sourceSha);
            }
            if (($release['draft'] ?? false) !== true) {
                throw new RuntimeException('GitHub release ' . $version . ' is already published; refusing to change released content.');
            }
            $this->uploadMissingAssets($client, $release, $manifest, $artifactDirectory);
        } else {
            $this->progress('Resuming from the saved draft release manifest.');
            if (($release['draft'] ?? false) !== true) {
                throw new RuntimeException('GitHub release ' . $version . ' is already published; refusing to change released content.');
            }
            if (!is_dir($artifactDirectory) && !mkdir($artifactDirectory, 0o700, true) && !is_dir($artifactDirectory)) {
                throw new RuntimeException('Could not create release resume directory.');
            }
            $client->downloadAsset($release, 'release-manifest.json', $savedManifestPath);
            $manifest = ReleaseManifest::fromJson((string) file_get_contents($savedManifestPath));
            if ($manifest->version() !== $version || $manifest->toArray()['release']['source_sha'] !== $sourceSha
                || $manifest->toArray()['release']['branch'] !== $branch || $manifest->toArray()['release']['main_branch'] !== $mainBranch
            ) {
                throw new RuntimeException('Saved release manifest does not match the requested version, source revision, and branches.');
            }
            $this->ensureMonorepoTag($version, $sourceSha, $root);
            $savedSplitPlan = $this->splitPlanFromManifest($manifest);
            $splitPlan = $this->splitPlanner->plan($version, $branch, $mainBranch, $sourceSha, $root);
            if ($splitPlan->toArray() !== $savedSplitPlan->toArray()) {
                throw new RuntimeException('Recomputed package splits differ from the persisted release manifest.');
            }
            $publicationPlan = $this->publisher->preflight($splitPlan, $root);
            if ($this->hasMissingPackageAssets($release, $manifest)) {
                $artifactDirectory = sys_get_temp_dir() . '/php-anonymizer-release-resume-' . bin2hex(random_bytes(8));
                $rebuilt = $this->buildManifest($splitPlan, $root, $artifactDirectory);
                if ($rebuilt->toArray() !== $manifest->toArray()) {
                    throw new RuntimeException('Rebuilt artifacts do not match the persisted release manifest; refusing to alter draft content.');
                }
                $this->uploadMissingAssets($client, $release, $manifest, $artifactDirectory);
            }
        }

        $this->progress('Checking release assets and verifying archive checksums.');
        $this->ensureArtifactsPresent($client, $release, $manifest, $artifactDirectory);
        $checksums = $artifactDirectory . '/SHA256SUMS';
        if (!is_file($checksums)) {
            $this->progress('Downloading release checksum manifest.');
            $client->downloadAsset($release, 'SHA256SUMS', $checksums);
        }
        $this->verifyArchiveChecksums($manifest, $artifactDirectory);
        $this->progress('Publishing package branches and tags.');
        $this->publisher->publish($publicationPlan, $root);
        $packagist = new PackagistReleaseVerifier();
        $this->progress('Requesting Packagist updates for all packages.');
        $packagist->triggerUpdates($manifest, getenv('PACKAGIST_USERNAME') ?: '', getenv('PACKAGIST_API_TOKEN') ?: '');
        $this->progress('Waiting for Packagist to publish the expected versions and source references.');
        $packagist->verify($manifest);
        $this->progress('Installing the exact release versions in a clean Composer consumer.');
        $packagist->installCleanConsumer($manifest);
        $this->progress('Publishing the GitHub release.');
        $client->publish($release);
        $this->progress('Opening or reusing the main-to-develop synchronization pull request.');
        $client->openOrReuseSynchronizationPullRequest();
        $this->progress('Release publication finished successfully.');
    }

    private function buildManifest(ReleaseSplitPlan $splitPlan, string $root, string $artifactDirectory): ReleaseManifest
    {
        $this->progress('Building archives and checksums from the selected source commit.');
        $persistDirectory = $artifactDirectory;
        $snapshot = sys_get_temp_dir() . '/php-anonymizer-release-artifacts-' . bin2hex(random_bytes(8));
        $added = false;

        try {
            $this->runGit($root, ['worktree', 'add', '--quiet', '--detach', $snapshot, $splitPlan->sourceSha]);
            $added = true;
            $this->runProcess([PHP_BINARY, $snapshot . '/tools/build-artifacts.php', $splitPlan->version], $snapshot);
            $artifactDirectory = $snapshot . '/var/artifacts/' . $splitPlan->version;
            $sourceManifest = json_decode((string) file_get_contents($artifactDirectory . '/release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($sourceManifest) || !is_array($sourceManifest['packages'] ?? null)) {
                throw new RuntimeException('Package archive builder returned an invalid manifest.');
            }
            $packages = [];
            foreach ($splitPlan->packages as $package => $split) {
                $archive = $sourceManifest['packages'][$package] ?? null;
                if (!is_array($archive)) {
                    throw new RuntimeException('Package archive builder omitted ' . $package . '.');
                }
                $packages[$package] = [
                    'composer_name' => $split['composer_name'],
                    'repository' => $split['repository'],
                    'publication_branch' => $split['publication_branch'],
                    'split_sha' => $split['split_sha'],
                    'archives' => [
                        'zip' => ['file' => $archive['zip'], 'sha256' => $archive['zip_sha256']],
                        'tar_gz' => ['file' => $archive['tar_gz'], 'sha256' => $archive['tar_gz_sha256']],
                    ],
                ];
            }
            $manifest = ReleaseManifest::fromArray([
                'schema_version' => ReleaseManifest::SCHEMA_VERSION,
                'release' => ['version' => $splitPlan->version, 'branch' => $splitPlan->branch, 'main_branch' => $splitPlan->mainBranch, 'source_sha' => $splitPlan->sourceSha],
                'packages' => $packages,
            ]);
            file_put_contents($artifactDirectory . '/release-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $this->writeChecksums($artifactDirectory);
            if (!is_dir(dirname($persistDirectory)) && !mkdir(dirname($persistDirectory), 0o700, true) && !is_dir(dirname($persistDirectory))) {
                throw new RuntimeException('Could not create artifact persistence directory.');
            }
            if (!rename($artifactDirectory, $persistDirectory)) {
                throw new RuntimeException('Could not persist release artifacts in the workflow workspace.');
            }
            $this->progress('Release manifest and archives saved to ' . $persistDirectory . '.');

            return $manifest;
        } finally {
            if ($added) {
                $this->runGit($root, ['worktree', 'remove', '--force', $snapshot]);
            }
        }
    }

    private function ensureMonorepoTag(string $version, string $sourceSha, string $root): void
    {
        $this->progress('Checking monorepo tag ' . $version . '.');
        $tagSha = $this->executeGit($root, ['rev-parse', '--verify', 'refs/tags/' . $version . '^{commit}'], false);
        if ($tagSha !== null) {
            if ($tagSha !== $sourceSha) {
                throw new RuntimeException('Monorepo tag ' . $version . ' already points at a different source revision.');
            }
        } else {
            $this->runGit($root, ['-c', 'user.name=PHP Anonymizer Release', '-c', 'user.email=dev@toniliesche.de', 'tag', '-a', $version, $sourceSha, '-m', 'Release ' . $version]);
        }
        $remoteListing = $this->runGit($root, ['ls-remote', '--tags', 'origin', 'refs/tags/' . $version, 'refs/tags/' . $version . '^{}']);
        $remoteTags = [];
        foreach (preg_split('/\R+/', $remoteListing) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{40}|[a-f0-9]{64})\s+refs\/tags\//', $line, $matches) === 1) {
                $remoteTags[] = $matches[1];
            }
        }
        if ($remoteTags !== []) {
            if (!in_array($sourceSha, $remoteTags, true)) {
                throw new RuntimeException('Monorepo remote tag ' . $version . ' already points at a different source revision.');
            }

            $this->progress('Monorepo tag ' . $version . ' already points to the selected source commit.');

            return;
        }
        $this->progress('Pushing monorepo tag ' . $version . '.');
        $this->runGit($root, ['push', 'origin', 'refs/tags/' . $version]);
    }

    private function splitPlanFromManifest(ReleaseManifest $manifest): ReleaseSplitPlan
    {
        $data = $manifest->toArray();
        $packages = [];
        foreach ($data['packages'] as $package => $entry) {
            $packages[$package] = [
                'composer_name' => $entry['composer_name'],
                'repository' => $entry['repository'],
                'publication_branch' => $entry['publication_branch'],
                'split_sha' => $entry['split_sha'],
            ];
        }

        return new ReleaseSplitPlan($manifest->version(), $data['release']['branch'], $data['release']['main_branch'], $data['release']['source_sha'], $packages);
    }

    private function ensureArtifactsPresent(GitHubReleaseClient $client, array $release, ReleaseManifest $manifest, string $directory): void
    {
        foreach ($manifest->toArray()['packages'] as $entry) {
            foreach ($entry['archives'] as $archive) {
                $path = $directory . '/' . $archive['file'];
                if (!is_file($path) || hash_file('sha256', $path) !== $archive['sha256']) {
                    $this->progress('Downloading missing or invalid release asset: ' . $archive['file'] . '.');
                    $client->downloadAsset($release, $archive['file'], $path);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $release
     */
    private function hasAsset(array $release, string $name): bool
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $release
     */
    private function hasMissingPackageAssets(array $release, ReleaseManifest $manifest): bool
    {
        foreach ($manifest->toArray()['packages'] as $entry) {
            foreach ($entry['archives'] as $archive) {
                if (!$this->hasAsset($release, $archive['file'])) {
                    return true;
                }
            }
        }

        return !$this->hasAsset($release, 'SHA256SUMS');
    }

    /**
     * @param array<string, mixed> $release
     */
    private function uploadMissingAssets(GitHubReleaseClient $client, array $release, ReleaseManifest $manifest, string $directory): void
    {
        $assets = ['release-manifest.json', 'SHA256SUMS'];
        foreach ($manifest->toArray()['packages'] as $entry) {
            $assets[] = $entry['archives']['zip']['file'];
            $assets[] = $entry['archives']['tar_gz']['file'];
        }
        $total = count($assets);
        foreach ($assets as $index => $name) {
            if (!$this->hasAsset($release, $name)) {
                $this->progress(sprintf('Uploading release asset %d/%d: %s.', $index + 1, $total, $name));
                $client->uploadAsset($release, $directory . '/' . $name);
            } else {
                $this->progress(sprintf('Release asset %d/%d already exists: %s.', $index + 1, $total, $name));
            }
        }
    }

    private function verifyArchiveChecksums(ReleaseManifest $manifest, string $directory): void
    {
        $verified = 0;
        foreach ($manifest->toArray()['packages'] as $entry) {
            foreach ($entry['archives'] as $archive) {
                $path = $directory . '/' . $archive['file'];
                if (!is_file($path) || hash_file('sha256', $path) !== $archive['sha256']) {
                    throw new RuntimeException('Release archive checksum mismatch: ' . $archive['file']);
                }
                $verified++;
            }
        }
        $this->progress(sprintf('Verified checksums for %d package archives.', $verified));
    }

    private function writeChecksums(string $directory): void
    {
        $lines = [];
        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== 'SHA256SUMS') {
                $lines[] = hash_file('sha256', $file) . '  ' . basename($file);
            }
        }
        sort($lines, SORT_STRING);
        file_put_contents($directory . '/SHA256SUMS', implode("\n", $lines) . "\n");
    }

    private function progress(string $message): void
    {
        fwrite(STDOUT, '[release] ' . $message . PHP_EOL);
        fflush(STDOUT);
    }

    private function resolveSource(string $sourceSha, string $root): string
    {
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $sourceSha) !== 1) {
            throw new InvalidArgumentException('Release source SHA must be a full commit SHA.');
        }
        $resolved = $this->runGit($root, ['rev-parse', '--verify', $sourceSha . '^{commit}']);
        if ($resolved !== $sourceSha) {
            throw new InvalidArgumentException('Release source SHA did not resolve exactly.');
        }

        return $resolved;
    }

    /**
     * @param list<string> $args
     */
    private function runGit(string $root, array $args): string
    {
        $result = $this->executeGit($root, $args, true);

        return $result ?? '';
    }

    /**
     * @param list<string> $args
     */
    private function executeGit(string $root, array $args, bool $required): ?string
    {
        $process = proc_open(['git', '-C', $root, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start release Git command.');
        }
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            if ($required) {
                throw new RuntimeException('Release Git command failed: ' . $stderr);
            }

            return null;
        }

        return $stdout;
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command, string $cwd): void
    {
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start release artifact builder.');
        }
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Release artifact build failed; see the preceding builder output.');
        }
    }
}
