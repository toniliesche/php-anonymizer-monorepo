<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class ReleaseTestFixture
{
    private string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/php-anonymizer-release-fixture-' . bin2hex(random_bytes(8));
        if (!mkdir($this->directory, 0o700)) {
            throw new RuntimeException('Could not create release fixture directory.');
        }
    }

    public function createMonorepo(): string
    {
        $repository = $this->directory . '/monorepo';
        mkdir($repository, 0o700);
        $this->initializeRepository($repository);
        file_put_contents($repository . '/CHANGELOG.md', "## Unreleased\n\n## 2.0.0\n\nFixture release.\n");
        file_put_contents($repository . '/composer.json', "{\"name\":\"php-anonymizer/anonymizer-dev\"}\n");

        $train = new ReleaseTrain();
        foreach ($train->packages() as $package) {
            $packageDirectory = $repository . '/packages/' . $package;
            mkdir($packageDirectory . '/src', 0o700, true);
            mkdir($packageDirectory . '/docs', 0o700);
            $metadata = [
                'name' => $train->composerNameFor($package),
                'require' => $package === 'anonymizer' ? [] : ['php-anonymizer/anonymizer' => '^2.0'],
                'extra' => ['branch-alias' => ['dev-develop' => ReleaseTrain::DEVELOPMENT_ALIAS]],
            ];
            if ($package === 'anonymizer-bundle') {
                $metadata['require']['php-anonymizer/anonymizer-symfony-serializer'] = '^2.0';
            }
            file_put_contents($packageDirectory . '/composer.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            file_put_contents($packageDirectory . '/README.md', '# ' . $package . " fixture\n");
            file_put_contents($packageDirectory . '/CHANGELOG.md', "## Unreleased\n\n## 2.0.0\n\nFixture release.\n");
            file_put_contents($packageDirectory . '/src/Fixture.php', "<?php\n\n// " . $package . " fixture\n");
            $relativeDocumentation = $train->documentationFor($package);
            foreach ($relativeDocumentation as $documentation) {
                $documentationPath = $packageDirectory . '/' . $documentation;
                $parent = dirname($documentationPath);
                if (!is_dir($parent)) {
                    mkdir($parent, 0o700, true);
                }
                file_put_contents($documentationPath, '# Fixture documentation' . "\n");
            }
        }

        $this->runGit($repository, ['add', '.']);
        $this->commit($repository, 'Create release fixture monorepo');
        $this->runGit($repository, ['checkout', '-b', 'release/2.0.x']);

        return $repository;
    }

    public function temporaryDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Create a legacy core checkout and bare remote with all published v0 tags.
     *
     * @return array{checkout: string, remote: string, tags: list<string>}
     */
    public function createLegacyCore(): array
    {
        $checkout = $this->directory . '/legacy-core';
        mkdir($checkout, 0o700);
        $this->initializeRepository($checkout);
        mkdir($checkout . '/src', 0o700);
        file_put_contents($checkout . '/composer.json', "{\"name\":\"php-anonymizer/anonymizer\"}\n");
        file_put_contents($checkout . '/README.md', "# Legacy PHP Anonymizer\n");
        file_put_contents($checkout . '/src/Anonymizer.php', "<?php\n\n// Preserved legacy core fixture\n");
        file_put_contents($checkout . '/CHANGELOG.md', "# Changelog\n\n");
        $this->runGit($checkout, ['add', '.']);
        $this->commit($checkout, 'Create legacy core history');

        $tags = ['0.1.0', '0.1.1', '0.1.2', '0.1.3', '0.1.4', '0.1.5', '0.1.6', '0.1.7', '0.1.8', '0.2.0', '0.3.0', '0.3.1', '0.4.0', '0.4.1', '0.4.2', '0.5.0'];
        foreach ($tags as $tag) {
            file_put_contents($checkout . '/CHANGELOG.md', '# Changelog' . "\n\n## " . $tag . "\n\nFixture history.\n");
            $this->runGit($checkout, ['add', 'CHANGELOG.md']);
            $this->commit($checkout, 'Record release ' . $tag);
            $this->runGit($checkout, ['tag', '-a', $tag, '-m', 'Release ' . $tag]);
        }

        $remote = $this->createBareDestination('php-anonymizer');
        $this->runGit($remote, ['symbolic-ref', 'HEAD', 'refs/heads/main']);
        $this->runGit($checkout, ['remote', 'add', 'fixture', $remote]);
        $this->runGit($checkout, ['push', 'fixture', 'main', '--tags']);

        return ['checkout' => $checkout, 'remote' => $remote, 'tags' => $tags];
    }

    public function createBareDestination(string $name): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) !== 1) {
            throw new RuntimeException('Fixture destination name is invalid.');
        }

        $remote = $this->directory . '/destinations/' . $name . '.git';
        if (!is_dir(dirname($remote)) && !mkdir(dirname($remote), 0o700, true)) {
            throw new RuntimeException('Could not create fixture destination directory.');
        }
        $process = proc_open(['git', 'init', '--bare', '--initial-branch=main', $remote], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not initialize bare fixture destination.');
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Could not initialize bare fixture destination: ' . $output);
        }

        return $remote;
    }

    public function cleanup(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(string $directory, array $arguments): string
    {
        $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a fixture Git command.');
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Fixture Git command failed: ' . $output);
        }

        return trim($output);
    }

    private function initializeRepository(string $directory): void
    {
        $this->runGit($directory, ['init', '--initial-branch=main']);
        $this->runGit($directory, ['config', 'user.email', 'release-fixture@example.test']);
        $this->runGit($directory, ['config', 'user.name', 'Release Fixture']);
        $this->runGit($directory, ['config', 'commit.gpgsign', 'false']);
    }

    private function commit(string $directory, string $message): void
    {
        $this->runGit($directory, ['commit', '-m', $message]);
    }
}
