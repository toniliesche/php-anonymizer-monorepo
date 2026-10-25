<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

$directory = __DIR__ . '/../.github/workflows';
$expected = ['quality.yml', 'release-preview.yml', 'benchmark.yml', 'release.yml'];
$workflows = [];
foreach ($expected as $filename) {
    $path = $directory . '/' . $filename;
    if (!is_file($path)) {
        throw new RuntimeException('Missing GitHub workflow: ' . $filename);
    }
    $workflow = Yaml::parseFile($path);
    if (!is_array($workflow) || !is_array($workflow['jobs'] ?? null) || !array_key_exists('on', $workflow)) {
        throw new RuntimeException('GitHub workflow structure is invalid: ' . $filename);
    }
    $content = (string) file_get_contents($path);
    if (preg_match('/^\s*uses:\s+[^\s]+@(?![a-f0-9]{40}(?:\s|$))[^\s]+/m', $content) === 1) {
        throw new RuntimeException('GitHub actions must be pinned to full commit SHAs: ' . $filename);
    }
    foreach (['actions/checkout@', 'actions/upload-artifact@'] as $action) {
        if (!str_contains($content, $action)) {
            throw new RuntimeException(sprintf('%s is missing required action %s', $filename, $action));
        }
    }
    foreach (['path: ${{ env.CI_DIAGNOSTICS_DIR }}/', 'if: always()'] as $artifactPolicy) {
        if (!str_contains($content, $artifactPolicy)) {
            throw new RuntimeException(sprintf('%s must preserve diagnostics even after failure (%s)', $filename, $artifactPolicy));
        }
    }
    $jobPermissions = array_first($workflow['jobs'])['permissions'] ?? null;
    $expectedContentsPermission = $filename === 'release.yml' ? 'write' : 'read';
    if (!is_array($jobPermissions) || ($jobPermissions['contents'] ?? null) !== $expectedContentsPermission) {
        throw new RuntimeException('Workflow job permissions are not explicitly constrained as expected: ' . $filename);
    }
    $workflows[$filename] = $workflow;
}

$quality = $workflows['quality.yml'];
if (!array_key_exists('pull_request', $quality['on']) || !array_key_exists('push', $quality['on'])
    || !str_contains((string) file_get_contents($directory . '/quality.yml'), 'composer qa')
    || !str_contains((string) file_get_contents($directory . '/quality.yml'), 'fetch-depth: 0')
    || !str_contains((string) file_get_contents($directory . '/quality.yml'), 'contents: read')
) {
    throw new RuntimeException('Quality workflow must run full QA on pull requests and pushes with read-only permissions and full history.');
}

$preview = $workflows['release-preview.yml'];
if (!isset($preview['on']['workflow_dispatch']['inputs']['release_branch'])
    || !isset($preview['on']['pull_request']['branches'])
) {
    throw new RuntimeException('Release preview must support release PRs and manual branch selection.');
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
            throw new RuntimeException('GitHub release preview must prepare local main from origin/main.');
        }
    }
    if (($step['name'] ?? null) === 'Preview release plan') {
        $previewRunStep = $index;
    }
}
if ($mainSetupStep === null || $previewRunStep === null || $mainSetupStep >= $previewRunStep
    || !str_contains((string) ($previewSteps[$previewRunStep]['run'] ?? ''), 'tools/release-preview.php "$RELEASE_BRANCH"')
) {
    throw new RuntimeException('GitHub release preview must prepare local main before calculating the release version.');
}

$benchmark = $workflows['benchmark.yml'];
if (($benchmark['on']['schedule'][0]['cron'] ?? null) !== '17 3 * * 1'
    || !array_key_exists('workflow_dispatch', $benchmark['on'])
    || !isset($benchmark['on']['pull_request']['paths'])
    || !str_contains((string) file_get_contents($directory . '/benchmark.yml'), 'benchmark.json')
) {
    throw new RuntimeException('Benchmark workflow lost its path filters, manual trigger, weekly schedule, or benchmark artifact.');
}

$release = $workflows['release.yml'];
$releaseContent = (string) file_get_contents($directory . '/release.yml');
$releaseJob = $release['jobs']['release'];
if (!isset($release['on']['workflow_dispatch']['inputs']['release_version'], $release['on']['workflow_dispatch']['inputs']['source_sha'], $release['on']['workflow_dispatch']['inputs']['release_branch'])
    || !str_contains($releaseContent, 'github.event.pull_request.merged == true')
    || !str_contains($releaseContent, 'github.event.pull_request.merge_commit_sha')
    || !str_contains($releaseContent, 'git merge-base --is-ancestor "$RELEASE_SOURCE_SHA" origin/main')
    || !str_contains($releaseContent, 'fetch-depth: 0')
    || !str_contains($releaseContent, 'fetch-tags: true')
    || !str_contains($releaseContent, 'cancel-in-progress: false')
    || !str_contains($releaseContent, 'composer ci:run-gate release-qa composer qa')
    || !str_contains($releaseContent, 'composer release:publish -- --execute')
    || ($releaseJob['permissions']['contents'] ?? null) !== 'write'
    || ($releaseJob['permissions']['pull-requests'] ?? null) !== 'write'
) {
    throw new RuntimeException('Release workflow is missing merged-revision, recovery, serialization, QA, or Task 6 publication wiring.');
}

$publishStep = null;
foreach ($releaseJob['steps'] as $step) {
    if (($step['name'] ?? null) === 'Publish or resume release') {
        $publishStep = $step;

        break;
    }
}
if (!is_array($publishStep)) {
    throw new RuntimeException('Release publication step was not found.');
}
foreach (['GITHUB_TOKEN', 'GH_TOKEN', 'PACKAGE_PUBLISH_TOKEN', 'PACKAGIST_USERNAME', 'PACKAGIST_API_TOKEN'] as $credential) {
    if (!array_key_exists($credential, $publishStep['env'] ?? [])) {
        throw new RuntimeException('Release publication step is missing credential ' . $credential);
    }
}
foreach ($releaseJob['steps'] as $step) {
    if (($step['name'] ?? null) !== 'Publish or resume release'
        && (str_contains((string) ($step['run'] ?? ''), 'PACKAGE_PUBLISH_TOKEN')
            || str_contains((string) ($step['run'] ?? ''), 'PACKAGIST_API_TOKEN')
            || str_contains(json_encode($step['env'] ?? [], JSON_THROW_ON_ERROR), 'secrets.'))
    ) {
        throw new RuntimeException('Publishing credentials must be confined to the publication step.');
    }
}

echo "GitHub workflow syntax and policy checks passed.\n";
