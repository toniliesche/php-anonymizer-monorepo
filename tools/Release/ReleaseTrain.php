<?php

declare(strict_types=1);

namespace PhpAnonymizer\Release;

use RuntimeException;

final class ReleaseTrain
{
    public const string DEVELOPMENT_ALIAS = '2.0.x-dev';

    /** @var list<string> */
    private const array PACKAGES = [
        'anonymizer',
        'anonymizer-bundle',
        'anonymizer-faker',
        'anonymizer-laravel',
        'anonymizer-personas',
        'anonymizer-pseudonymization',
        'anonymizer-symfony-serializer',
    ];

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
    public function packages(): array
    {
        return self::PACKAGES;
    }

    /**
     * @return list<string>
     */
    public function documentationFor(string $package): array
    {
        return self::DOCUMENTATION[$package] ?? throw new RuntimeException('Unknown package: ' . $package);
    }

    public function requiredCoreConstraint(string $package): ?string
    {
        return $package === 'anonymizer' ? null : '^2.0';
    }

    public function requiredSerializerConstraint(string $package): ?string
    {
        return $package === 'anonymizer-bundle' ? '^2.0' : null;
    }
}
