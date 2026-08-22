<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use RuntimeException;

final class ReleaseReadiness
{
    /** @var array<string, list<string>> */
    private const array DOCUMENTATION = [
        'anonymizer' => ['docs/user-guide.md', 'docs/migration-v2.md'],
        'anonymizer-bundle' => ['docs/usage.md'],
        'anonymizer-faker' => ['docs/usage.md'],
        'anonymizer-laravel' => ['docs/usage.md'],
        'anonymizer-personas' => ['docs/usage.md'],
        'anonymizer-pseudonymization' => ['docs/usage.md'],
        'anonymizer-symfony-serializer' => ['docs/usage.md'],
    ];

    /**
     * @return list<string>
     */
    public function validate(string $version, string $root): array
    {
        $errors = [];
        $rootChangelog = $root . '/CHANGELOG.md';
        if (!$this->hasHeading($rootChangelog, $version)) {
            $errors[] = 'Root changelog is missing release ' . $version . '.';
        }

        foreach (self::DOCUMENTATION as $package => $documentation) {
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
            if (!$this->hasHeading($directory . '/CHANGELOG.md', $version)) {
                $errors[] = $package . ' changelog is missing release ' . $version . '.';
            }
            foreach ($documentation as $relative) {
                if (!is_file($directory . '/' . $relative)) {
                    $errors[] = $package . ' is missing ' . $relative . '.';
                }
            }

            $alias = $metadata['extra']['branch-alias']['dev-develop'] ?? null;
            if ($alias !== '2.0.x-dev') {
                $errors[] = $package . ' must declare the 2.0.x-dev branch alias.';
            }
            if ($package !== 'anonymizer' && ($metadata['require']['php-anonymizer/anonymizer'] ?? null) !== '^2.0') {
                $errors[] = $package . ' must require php-anonymizer/anonymizer ^2.0.';
            }
            if ($package === 'anonymizer-bundle' && ($metadata['require']['php-anonymizer/anonymizer-symfony-serializer'] ?? null) !== '^2.0') {
                $errors[] = 'anonymizer-bundle must require the Serializer package ^2.0.';
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
        return self::DOCUMENTATION[$package] ?? throw new RuntimeException('Unknown package: ' . $package);
    }

    private function hasHeading(string $file, string $version): bool
    {
        if (!is_file($file)) {
            return false;
        }

        return str_contains((string) file_get_contents($file), '## ' . $version);
    }
}
