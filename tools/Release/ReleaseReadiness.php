<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use RuntimeException;

final class ReleaseReadiness
{
    /**
     * @return list<string>
     */
    public function validate(string $version, string $root): array
    {
        $train = new ReleaseTrain();
        $errors = [];
        $rootChangelog = $root . '/CHANGELOG.md';
        if (!$this->hasReleaseSections($rootChangelog, $version)) {
            $errors[] = 'Root changelog must place Unreleased before release ' . $version . '.';
        }

        foreach ($train->packages() as $package) {
            $directory = $root . '/packages/' . $package;
            $manifest = $directory . '/composer.json';
            if (!is_file($manifest)) {
                $errors[] = 'Expected package is missing: ' . $package . '.';

                continue;
            }
            $metadata = json_decode((string) file_get_contents($manifest), true);
            if (!is_array($metadata)) {
                $errors[] = 'Invalid Composer metadata: ' . $package . '.';

                continue;
            }
            foreach (['README.md', 'CHANGELOG.md'] as $required) {
                if (!is_file($directory . '/' . $required)) {
                    $errors[] = $package . ' is missing ' . $required . '.';
                }
            }
            if (!is_dir($directory . '/src')) {
                $errors[] = $package . ' is missing its src directory.';
            }
            if (!$this->hasReleaseSections($directory . '/CHANGELOG.md', $version)) {
                $errors[] = $package . ' changelog must place Unreleased before release ' . $version . '.';
            }
            foreach ($train->documentationFor($package) as $relative) {
                if (!is_file($directory . '/' . $relative)) {
                    $errors[] = $package . ' is missing ' . $relative . '.';
                }
            }

            if (($metadata['name'] ?? null) !== 'php-anonymizer/' . $package) {
                $errors[] = $package . ' must declare Composer name php-anonymizer/' . $package . '.';
            }
            $alias = $metadata['extra']['branch-alias']['dev-develop'] ?? null;
            if ($alias !== ReleaseTrain::DEVELOPMENT_ALIAS) {
                $errors[] = $package . ' must declare the ' . ReleaseTrain::DEVELOPMENT_ALIAS . ' branch alias.';
            }
            if ($train->requiredCoreConstraint($package) !== null && ($metadata['require']['php-anonymizer/anonymizer'] ?? null) !== $train->requiredCoreConstraint($package)) {
                $errors[] = $package . ' must require php-anonymizer/anonymizer ^2.0.';
            }
            if ($package === 'anonymizer-bundle' && array_key_exists('php-anonymizer/anonymizer-symfony-serializer', $metadata['require'] ?? [])) {
                $errors[] = 'anonymizer-bundle must keep the Serializer adapter optional.';
            }
        }

        return $errors;
    }

    public function assert(string $version, string $root): void
    {
        $errors = $this->validate($version, $root);
        if ($errors !== []) {
            throw new RuntimeException("Release readiness failed:\n- " . implode("\n- ", $errors));
        }
    }

    /**
     * @return list<string>
     */
    public function documentationFor(string $package): array
    {
        return new ReleaseTrain()->documentationFor($package);
    }

    private function hasReleaseSections(string $file, string $version): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $contents = (string) file_get_contents($file);
        $unreleased = strpos($contents, '## Unreleased');
        $release = strpos($contents, '## ' . $version);

        return $unreleased !== false && $release !== false && $unreleased < $release;
    }
}
