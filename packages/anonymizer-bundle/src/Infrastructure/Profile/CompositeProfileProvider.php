<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

// @internal
final readonly class CompositeProfileProvider implements ProfileProviderInterface
{
    /**
     * @param iterable<ProfileProviderInterface> $providers
     */
    public function __construct(
        private ProfileProviderInterface $configured,
        private iterable $providers,
    ) {
    }

    /**
     * @return iterable<ProfileDefinition>
     */
    #[Override]
    public function profiles(): iterable
    {
        yield from $this->configured->profiles();
        foreach ($this->providers as $provider) {
            yield from $provider->profiles();
        }
    }
}
