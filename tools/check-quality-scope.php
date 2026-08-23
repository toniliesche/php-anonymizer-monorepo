<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$published = [];
foreach (glob($root . '/packages/*/composer.json') ?: [] as $manifest) {
    $packageRoot = dirname($manifest);
    if (is_dir($packageRoot . '/src')) {
        $published[] = relativePath($root, $packageRoot . '/src');
    }
}

$published = uniqueSorted($published);
$phpunit = parsePhpunitScope($root . '/phpunit.xml');
$infection = parseInfectionScope($root . '/infection.json5');
assertExactScope('PHPUnit', $published, $phpunit);
assertExactScope('Infection', $published, $infection['directories']);

if ($infection['excludes'] !== []) {
    fail('Infection source.excludes is not permitted: ' . implode(', ', $infection['excludes']));
}

$violations = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/packages', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php' || !str_contains(str_replace('\\', '/', $file->getPathname()), '/src/')) {
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        fail(sprintf('Unable to read source file: %s', relativePath($root, $file->getPathname())));
    }
    foreach (preg_split('/\R/', $contents) ?: [] as $lineNumber => $line) {
        if (str_contains($line, '@infection-ignore-next-line') && preg_match('/@infection-ignore-next-line\b\s+\S+/i', $line) !== 1) {
            $violations[] = sprintf('%s:%d: mutation suppression requires an explanation', relativePath($root, $file->getPathname()), $lineNumber + 1);
        }
        if (preg_match('/@infection-ignore(?!-next-line\b)/i', $line) === 1) {
            $violations[] = sprintf('%s:%d: broad mutation suppression is not permitted', relativePath($root, $file->getPathname()), $lineNumber + 1);
        }
    }
}
if ($violations !== []) {
    fail("Invalid Infection suppression(s):\n- " . implode("\n- ", $violations));
}

printf("Quality scope covers %d published packages in PHPUnit and Infection.\n", count($published));

/**
 * @return list<string>
 */
function parsePhpunitScope(string $file): array
{
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        fail(sprintf('PHPUnit configuration is not valid XML: %s', $file));
    }
    $paths = [];
    foreach ($xml->source->include->directory ?? [] as $directory) {
        $paths[] = (string) $directory;
    }

    return uniqueSorted($paths);
}

/**
 * @return array{directories: list<string>, excludes: list<string>}
 */
function parseInfectionScope(string $file): array
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        fail(sprintf('Unable to read Infection configuration: %s', $file));
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded) || !is_array($decoded['source'] ?? null)) {
        fail(sprintf('Infection configuration must be valid JSON5 with a source object: %s', $file));
    }
    $directories = $decoded['source']['directories'] ?? null;
    $excludes = $decoded['source']['excludes'] ?? [];
    if (!is_array($directories) || !is_array($excludes) || array_filter($directories, is_string(...)) !== $directories || array_filter($excludes, is_string(...)) !== $excludes) {
        fail(sprintf('Infection source directories and excludes must be string arrays: %s', $file));
    }

    return ['directories' => uniqueSorted($directories), 'excludes' => uniqueSorted($excludes)];
}

/**
 * @param list<string> $expected @param list<string> $actual
 */
function assertExactScope(string $tool, array $expected, array $actual): void
{
    $missing = array_values(array_diff($expected, $actual));
    $unknown = array_values(array_diff($actual, $expected));
    if ($missing === [] && $unknown === [] && count($actual) === count(array_unique($actual))) {
        return;
    }
    $details = [];
    if ($missing !== []) {
        $details[] = 'missing: ' . implode(', ', $missing);
    }
    if ($unknown !== []) {
        $details[] = 'unknown: ' . implode(', ', $unknown);
    }
    if (count($actual) !== count(array_unique($actual))) {
        $details[] = 'duplicate paths are not permitted';
    }
    fail($tool . ' quality scope mismatch (' . implode('; ', $details) . ').');
}

/**
 * @param list<string> $paths @return list<string>
 */
function uniqueSorted(array $paths): array
{
    $paths = array_map(static fn (string $path): string => trim(str_replace('\\', '/', $path), '/'), $paths);
    $paths = array_values(array_unique($paths));
    sort($paths);

    return $paths;
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");

    exit(1);
}
