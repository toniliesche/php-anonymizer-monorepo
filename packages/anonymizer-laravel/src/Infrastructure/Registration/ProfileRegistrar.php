<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Registration;

// @codeCoverageIgnoreStart
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspector;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile\ProfileComposer;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final readonly class ProfileRegistrar
{
    /**
     * @param callable(string, mixed): mixed $configuration
     */
    public function register(Container $app, callable $configuration, string $profileTag): void
    {
        $app->singleton(ProfileCollection::class, function (Container $app) use ($configuration, $profileTag): ProfileCollection {
            $configured = $configuration('anonymizer.profiles', []);
            if (!is_array($configured)) {
                throw new InvalidArgumentException('anonymizer.profiles must be a map.');
            }

            Assert::isMap($configured);

            return $app->make(ProfileComposer::class)->compose($configured, $app->tagged($profileTag));
        });
        $app->singleton(ProfileInspector::class, fn (Container $app): ProfileInspector => new ProfileInspector($app->make(ProfileCollection::class)));
        $app->alias(ProfileInspector::class, ProfileInspectorInterface::class);
    }
}
// @codeCoverageIgnoreEnd
