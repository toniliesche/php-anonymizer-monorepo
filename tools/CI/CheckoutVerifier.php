<?php

declare(strict_types=1);

namespace PhpAnonymizer\CI;

use RuntimeException;

final class CheckoutVerifier
{
    /**
     * @return array{revision: string, baseline: string, tags: list<string>}
     */
    public function verify(string $root, string $expectedRevision, string $baselineRef = 'origin/main'): array
    {
        $root = realpath($root) ?: throw new RuntimeException('Checkout path does not exist.');
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $expectedRevision) !== 1) {
            throw new RuntimeException('Expected event revision must be a full Git commit SHA.');
        }
        if ($this->git($root, ['rev-parse', '--is-shallow-repository']) !== 'false') {
            throw new RuntimeException('Release checkout must contain complete Git history.');
        }
        $revision = $this->git($root, ['rev-parse', '--verify', 'HEAD^{commit}']);
        if ($revision !== $expectedRevision) {
            throw new RuntimeException('Checkout HEAD does not match the event revision.');
        }
        $tagsOutput = $this->git($root, ['tag', '--list']);
        $tags = array_values(array_filter(preg_split('/\R+/', $tagsOutput) ?: [], static fn (string $tag): bool => $tag !== ''));
        if ($tags === []) {
            throw new RuntimeException('Checkout has no fetched tags.');
        }
        foreach ($tags as $tag) {
            $this->git($root, ['cat-file', '-e', 'refs/tags/' . $tag . '^{}']);
        }
        $baseline = $this->git($root, ['rev-parse', '--verify', $baselineRef . '^{commit}']);
        $baselineAncestor = $this->executeGit($root, ['merge-base', '--is-ancestor', $baseline, $revision]);
        $revisionAncestor = $this->executeGit($root, ['merge-base', '--is-ancestor', $revision, $baseline]);
        if ($baselineAncestor !== 0 && $revisionAncestor !== 0) {
            throw new RuntimeException('Event revision and configured main baseline ' . $baselineRef . ' are not in the same history.');
        }

        return ['revision' => $revision, 'baseline' => $baseline, 'tags' => $tags];
    }

    /**
     * @param list<string> $arguments
     */
    private function git(string $root, array $arguments): string
    {
        $process = proc_open(['git', '-C', $root, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start checkout verification Git command.');
        }
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Checkout verification Git command failed: ' . $stderr);
        }

        return $stdout;
    }

    /**
     * @param list<string> $arguments
     */
    private function executeGit(string $root, array $arguments): int
    {
        $process = proc_open(['git', '-C', $root, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start ancestry verification.');
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
