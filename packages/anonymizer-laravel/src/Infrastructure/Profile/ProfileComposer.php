<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile;

use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use Webmozart\Assert\Assert;

// @internal
final class ProfileComposer
{
    /**
     * @param array<mixed, mixed> $configured
     * @param iterable<mixed> $providers
     */
    public function compose(array $configured, iterable $providers): ProfileCollection
    {
        $profiles = [];
        foreach ($configured as $name => $definition) {
            Assert::string($name, 'Profile names must be strings.');
            Assert::isArray($definition, sprintf('Profile "%s" must be a map.', $name));
            Assert::allString(array_keys($definition), sprintf('Profile "%s" must use string keys.', $name));
            Assert::isMap($definition, sprintf('Profile "%s" must be a map.', $name));
            $profiles[] = ProfileDefinition::fromArray($name, $definition);
        }
        foreach ($providers as $provider) {
            if (!$provider instanceof ProfileProviderInterface) {
                throw new InvalidArgumentException(sprintf('Profile provider must implement %s, got %s.', ProfileProviderInterface::class, get_debug_type($provider)));
            }
            foreach ($provider->profiles() as $profile) {
                $profiles[] = $profile;
            }
        }

        return new ProfileCollection($profiles);
    }
}
