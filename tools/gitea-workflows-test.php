<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

$directory = __DIR__ . '/../.gitea/workflows';
$expected = ['quality.yaml', 'release-preview.yaml', 'benchmark.yaml'];
foreach ($expected as $filename) {
    $path = $directory . '/' . $filename;
    if (!is_file($path)) {
        throw new RuntimeException('Missing Gitea workflow: ' . $filename);
    }
    $workflow = Yaml::parseFile($path);
    if (!is_array($workflow) || !is_array($workflow['jobs'] ?? null) || !isset($workflow['on'])) {
        throw new RuntimeException('Gitea workflow structure is invalid: ' . $filename);
    }
    $content = (string) file_get_contents($path);
    foreach (['runs-on: linux-amd64-docker', 'registry.toniliesche.de/development/php-ci-builder:1.0', 'gitea-upload-artifact@', 'gitea-download-artifact@', 'round trip'] as $required) {
        if (!str_contains($content, $required)) {
            throw new RuntimeException(sprintf('%s does not declare required runtime or diagnostics setting: %s', $filename, $required));
        }
    }
    if (!str_contains($content, 'sh tools/install-git-subtree.sh')) {
        throw new RuntimeException('Gitea workflow must install and verify git-subtree: ' . $filename);
    }
    if (str_contains($content, 'PACKAGE_PUBLISH_TOKEN') || str_contains($content, 'PACKAGIST_API_TOKEN') || str_contains($content, 'GITHUB_TOKEN')) {
        throw new RuntimeException('Validation workflow must not receive publishing credentials: ' . $filename);
    }
    if (!str_contains($content, 'github.event.pull_request.head.repo.full_name == github.repository')) {
        throw new RuntimeException('Private builder access must be limited to trusted pull request events: ' . $filename);
    }
}

$shellCheck = proc_open(['sh', '-n', __DIR__ . '/../tools/install-git-subtree.sh'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($shellCheck)) {
    throw new RuntimeException('Could not validate the git-subtree installer shell syntax.');
}
$shellError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($shellCheck) !== 0) {
    throw new RuntimeException('git-subtree installer shell syntax is invalid: ' . $shellError);
}

$quality = Yaml::parseFile($directory . '/quality.yaml');
if (!isset($quality['on']['pull_request'], $quality['on']['push']) || !str_contains((string) file_get_contents($directory . '/quality.yaml'), 'composer qa')) {
    throw new RuntimeException('Quality workflow must run full QA for pull requests and branch pushes.');
}
$preview = Yaml::parseFile($directory . '/release-preview.yaml');
if (!isset($preview['on']['workflow_dispatch']['inputs']['release_branch']) || !isset($preview['on']['pull_request']['branches'])) {
    throw new RuntimeException('Release preview must support release-to-main PRs and an explicit manual branch.');
}
$previewSteps = $preview['jobs']['preview']['steps'];
$mainSetupStep = null;
$previewRunStep = null;
foreach ($previewSteps as $index => $step) {
    if (($step['name'] ?? null) === 'Prepare local main ref for version calculation') {
        $mainSetupStep = $index;
        if (!str_contains((string) ($step['run'] ?? ''), 'git fetch --no-tags origin')
            || !str_contains((string) ($step['run'] ?? ''), 'git branch --force main origin/main')
        ) {
            throw new RuntimeException('Gitea release preview must prepare local main from origin/main.');
        }
    }
    if (($step['name'] ?? null) === 'Run release preview without publication credentials') {
        $previewRunStep = $index;
    }
}
if ($mainSetupStep === null || $previewRunStep === null || $mainSetupStep >= $previewRunStep) {
    throw new RuntimeException('Gitea release preview must prepare local main before calculating the release version.');
}
$benchmark = Yaml::parseFile($directory . '/benchmark.yaml');
if (($benchmark['on']['schedule'][0]['cron'] ?? null) !== '17 3 * * 1'
    || !array_key_exists('workflow_dispatch', $benchmark['on'])
    || !isset($benchmark['on']['pull_request']['paths'])
) {
    throw new RuntimeException('Benchmark workflow lost its path filters, manual trigger, or weekly schedule.');
}

echo "Gitea workflow syntax and policy checks passed.\n";
