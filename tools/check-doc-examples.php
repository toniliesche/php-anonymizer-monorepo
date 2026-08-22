<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/docs/*.md') ?: [],
    glob($root . '/packages/*/docs/*.md') ?: [],
);
sort($files, SORT_STRING);

$examples = [];
foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Could not read documentation file: ' . $file);
    }

    preg_match_all('/<!-- executable -->\s*\R```php\s*\R(.*?)```/s', $contents, $matches);
    foreach ($matches[1] as $index => $source) {
        $examples[] = [$file, $index + 1, $source];
    }
}

$directory = sys_get_temp_dir() . '/php-anonymizer-doc-examples-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0o777, true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create documentation example directory.');
}

try {
    if (!symlink($root . '/vendor', $directory . '/vendor')) {
        throw new RuntimeException('Could not link Composer dependencies for documentation examples.');
    }

    foreach ($examples as $example) {
        [$file, $number, $source] = $example;
        $script = $directory . '/example-' . $number . '.php';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__ . '/vendor/autoload.php';\n\n" . trim($source) . "\n";
        file_put_contents($script, $contents);

        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start documentation example.');
        }
        $output = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException(sprintf(
                'Documentation example failed: %s fence %d%s',
                substr($file, strlen($root) + 1),
                $number,
                $output === '' ? '' : PHP_EOL . $output,
            ));
        }
    }
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        is_link($file) || is_file($file) ? unlink($file) : rmdir($file);
    }
    rmdir($directory);
}

printf("Executed %d documentation examples.\n", count($examples));
