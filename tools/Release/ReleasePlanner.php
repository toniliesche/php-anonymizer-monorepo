<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use InvalidArgumentException;
use RuntimeException;

final class ReleasePlanner
{
    /** @return array{branch: string, main: string, prefix: string, version: string, tags: list<string>} */
    public function plan(string $branch, string $main, string $root): array
    {
        if (preg_match('/^release\/(\d+)\.(\d+)\.x$/', $branch, $matches) !== 1) {
            throw new InvalidArgumentException("Release preview requires a release/X.Y.x branch; received: {$branch}");
        }

        $this->run(['git', 'rev-parse', '--verify', $main], $root);
        $prefix = $matches[1] . '.' . $matches[2] . '.';
        $tags = trim($this->run(['git', 'tag', '--merged', $main, '--list', $prefix . '*'], $root));
        $stable = [];
        foreach (preg_split('/\R+/', $tags) ?: [] as $tag) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $tag) === 1) {
                $stable[] = $tag;
            }
        }
        usort($stable, version_compare(...));
        $patch = $stable === [] ? 0 : ((int) substr(array_last($stable), strlen($prefix))) + 1;

        return ['branch' => $branch, 'main' => $main, 'prefix' => $prefix, 'version' => $prefix . $patch, 'tags' => $stable];
    }

    /** @param list<string> $command */
    private function run(array $command, string $cwd): string
    {
        $escaped = implode(' ', array_map(escapeshellarg(...), $command));
        $process = proc_open($escaped, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start Git command.');
        }
        $output = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error !== '' ? $error : 'Git command failed.');
        }

        return $output;
    }
}
