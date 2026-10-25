<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

// @internal
final readonly class ConfiguredProfileProvider implements ProfileProviderInterface
{
    /**
     * @param list<ProfileDefinition> $profiles
     */
    public function __construct(private array $profiles)
    {
    }

    /**
     * @return iterable<ProfileDefinition>
     */
    #[Override]
    public function profiles(): iterable
    {
        yield from $this->profiles;
    }
}
