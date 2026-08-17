<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-release-' . bin2hex(random_bytes(6));
mkdir($directory, 0777, true);

$run = static function (array $command, string $cwd): string {
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start release fixture command.');
    }

    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException($output);
    }

    return $output;
};

try {
    $run(['git', 'init', '-b', 'main'], $directory);
    $run(['git', 'config', 'user.email', 'fixture@example.test'], $directory);
    $run(['git', 'config', 'user.name', 'Release Fixture'], $directory);
    file_put_contents($directory . '/README.md', "fixture\n");
    $run(['git', 'add', 'README.md'], $directory);
    $run(['git', 'commit', '-m', 'initial'], $directory);
    $run(['git', 'tag', '1.0.0'], $directory);
    $run(['git', 'checkout', '-b', 'release/1.0.x'], $directory);

    $output = $run([PHP_BINARY, $root . '/tools/release-preview.php', 'release/1.0.x', 'main'], $directory);
    if (!str_contains($output, 'next version: 1.0.1')) {
        throw new RuntimeException('Release preview did not calculate 1.0.1.');
    }

    echo "Release preview fixture passed (1.0.1).\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
