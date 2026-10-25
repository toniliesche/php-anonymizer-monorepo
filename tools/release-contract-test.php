<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseManifest;
use PhpAnonymizer\Release\ReleaseTrain;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseManifest.php';

$train = new ReleaseTrain();

$expectedRepositories = [
    'anonymizer' => 'https://github.com/toniliesche/php-anonymizer.git',
    'anonymizer-bundle' => 'https://github.com/toniliesche/php-anonymizer-bundle.git',
    'anonymizer-faker' => 'https://github.com/toniliesche/php-anonymizer-faker.git',
    'anonymizer-laravel' => 'https://github.com/toniliesche/php-anonymizer-laravel.git',
    'anonymizer-personas' => 'https://github.com/toniliesche/php-anonymizer-personas.git',
    'anonymizer-pseudonymization' => 'https://github.com/toniliesche/php-anonymizer-pseudonymization.git',
    'anonymizer-symfony-serializer' => 'https://github.com/toniliesche/php-anonymizer-symfony-serializer.git',
];
foreach ($expectedRepositories as $package => $repository) {
    if ($train->composerNameFor($package) !== 'php-anonymizer/' . $package || $train->repositoryFor($package) !== $repository) {
        throw new RuntimeException('Release destination mismatch for ' . $package);
    }
}

if ($train->publicationBranchFor('2.0.0') !== '2.x' || $train->publicationBranchFor('11.14.9') !== '11.x') {
    throw new RuntimeException('Publication branch must follow the release major version.');
}
assertContractFailure(static fn (): string => $train->repositoryFor('unknown-package'));
assertContractFailure(static fn (): string => $train->publicationBranchFor('2.0.0-beta1'));
assertContractFailure(static fn (): string => $train->publicationBranchFor('02.0.0'));

$packages = [];
foreach ($train->packages() as $package) {
    $packages[$package] = [
        'composer_name' => $train->composerNameFor($package),
        'repository' => $train->repositoryFor($package),
        'publication_branch' => $train->publicationBranchFor('2.0.0'),
        'split_sha' => str_repeat('a', 40),
        'archives' => [
            'zip' => ['file' => $package . '-2.0.0.zip', 'sha256' => str_repeat('b', 64)],
            'tar_gz' => ['file' => $package . '-2.0.0.tar.gz', 'sha256' => str_repeat('c', 64)],
        ],
    ];
}
$document = [
    'schema_version' => ReleaseManifest::SCHEMA_VERSION,
    'release' => [
        'version' => '2.0.0',
        'branch' => 'release/2.0.x',
        'main_branch' => 'main',
        'source_sha' => str_repeat('d', 40),
    ],
    'packages' => $packages,
];
$manifest = ReleaseManifest::fromArray($document);
if ($manifest->toArray() !== $document || ReleaseManifest::fromJson(json_encode($document, JSON_THROW_ON_ERROR))->toArray() !== $document) {
    throw new RuntimeException('Release manifest did not survive an array or JSON round trip.');
}
$reordered = array_reverse($document, true);
$reordered['release'] = array_reverse($document['release'], true);
$reordered['packages'] = array_reverse($document['packages'], true);
if (!ReleaseManifest::fromArray($reordered) instanceof ReleaseManifest) {
    throw new RuntimeException('Release manifest depended on JSON object member order.');
}

$missingPackage = $document;
unset($missingPackage['packages']['anonymizer-personas']);
assertContractFailure(static fn (): ReleaseManifest => ReleaseManifest::fromArray($missingPackage));

$wrongVersionBranch = $document;
$wrongVersionBranch['release']['branch'] = 'release/2.1.x';
assertContractFailure(static fn (): ReleaseManifest => ReleaseManifest::fromArray($wrongVersionBranch));

$wrongRepository = $document;
$wrongRepository['packages']['anonymizer']['repository'] = 'https://example.test/package.git';
assertContractFailure(static fn (): ReleaseManifest => ReleaseManifest::fromArray($wrongRepository));

$wrongArchiveHash = $document;
$wrongArchiveHash['packages']['anonymizer']['archives']['zip']['sha256'] = 'invalid';
assertContractFailure(static fn (): ReleaseManifest => ReleaseManifest::fromArray($wrongArchiveHash));

echo 'Release contracts passed for ' . count($train->packages()) . " packages.\n";

/**
 * @param Closure(): mixed $operation
 */
function assertContractFailure(Closure $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    throw new RuntimeException('Invalid release contract input was accepted.');
}
