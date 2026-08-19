<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport;

use Symfony\Component\Yaml\Yaml;

/** @codeCoverageIgnore Rendering adapter is covered by command integration tests. */
final class BundleConfigRenderer
{
    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        return Yaml::dump($config, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK) . "\n";
    }
}
