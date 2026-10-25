<?php

declare(strict_types=1);

namespace PhpAnonymizer\CI;

use RuntimeException;

final class GiteaRuntimeVerifier
{
    /**
     * @return array{php: string, xdebug: string, pcov_enabled_for_coverage: bool, git_exec_path: string, git_subtree: string, extensions: list<string>}
     */
    public function verify(string $root): array
    {
        $root = realpath($root) ?: throw new RuntimeException('Repository root does not exist.');
        $lockPath = $root . '/composer.lock';
        if (!is_file($lockPath)) {
            throw new RuntimeException('composer.lock is required for pre-install runtime validation.');
        }
        $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($lock)) {
            throw new RuntimeException('composer.lock is invalid.');
        }
        $requiredExtensions = [];
        foreach (['platform', 'platform-dev'] as $platform) {
            foreach ($lock[$platform] ?? [] as $name => $_constraint) {
                if (str_starts_with((string) $name, 'ext-')) {
                    $requiredExtensions[substr((string) $name, 4)] = true;
                }
            }
        }
        foreach (['packages', 'packages-dev'] as $set) {
            foreach ($lock[$set] ?? [] as $package) {
                foreach ($package['require'] ?? [] as $name => $_constraint) {
                    if (str_starts_with((string) $name, 'ext-')) {
                        $requiredExtensions[substr((string) $name, 4)] = true;
                    }
                }
            }
        }
        $missing = [];
        foreach (array_keys($requiredExtensions) as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }
        sort($missing, SORT_STRING);
        if ($missing !== []) {
            throw new RuntimeException('Missing extensions required by the locked dependencies: ' . implode(', ', $missing));
        }
        if (!extension_loaded('xdebug')) {
            throw new RuntimeException('The Gitea PHP runtime requires the Xdebug extension.');
        }
        $xdebugVersion = phpversion('xdebug') ?: 'unknown';
        $xdebugModes = array_map(trim(...), explode(',', (string) ini_get('xdebug.mode')));
        if (!in_array('coverage', $xdebugModes, true) && getenv('XDEBUG_MODE') !== 'coverage') {
            throw new RuntimeException('Xdebug must have coverage mode enabled. Set XDEBUG_MODE=coverage.');
        }
        $pcovEnabledForCoverage = false;
        if (extension_loaded('pcov')) {
            $coverageRunner = file_get_contents($root . '/tools/run-coverage.php');
            if (!is_string($coverageRunner) || !str_contains($coverageRunner, '-d pcov.enabled=0')) {
                throw new RuntimeException('The project coverage runner must disable PCOV when Xdebug is used.');
            }
        }
        $execPath = $this->command(['git', '--exec-path']);
        $subtree = rtrim($execPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'git-subtree';
        if (!is_file($subtree) || !is_executable($subtree)) {
            throw new RuntimeException('git-subtree must be installed in Git exec path: ' . $execPath);
        }
        $extensions = array_keys($requiredExtensions);
        sort($extensions, SORT_STRING);

        return [
            'php' => PHP_VERSION,
            'xdebug' => $xdebugVersion,
            'pcov_enabled_for_coverage' => $pcovEnabledForCoverage,
            'git_exec_path' => $execPath,
            'git_subtree' => $subtree,
            'extensions' => $extensions,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function command(array $arguments): string
    {
        $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start runtime prerequisite command.');
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Runtime prerequisite command failed (' . implode(' ', $arguments) . '): ' . $stderr);
        }

        return trim($stdout);
    }
}
