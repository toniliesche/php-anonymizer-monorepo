<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Registration;

// @codeCoverageIgnoreStart
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ValueTransformationInterface;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final readonly class CoreRegistrar
{
    /** @param callable(string, mixed): mixed $configuration
     * @param callable(array<string, mixed>, string, int): int $limit
     */
    public function register(Container $app, callable $configuration, callable $limit, string $strategyTag, string $codecTag, string $transformationTag): void
    {
        $app->singleton(AnonymizationLimits::class, function () use ($configuration, $limit): AnonymizationLimits {
            $limits = $configuration('anonymizer.limits', []);
            if (!is_array($limits)) {
                throw new InvalidArgumentException('anonymizer.limits must be a map.');
            }

            Assert::isMap($limits);

            return new AnonymizationLimits(
                $limit($limits, 'max_input_bytes', 10_485_760),
                $limit($limits, 'max_depth', 64),
                $limit($limits, 'max_visited_nodes', 100_000),
                $limit($limits, 'max_collection_entries', 50_000),
                $limit($limits, 'max_embedded_payload_depth', 8),
            );
        });
        $app->singleton(AnonymizerBuilder::class, function (Container $app) use ($strategyTag, $codecTag, $transformationTag): AnonymizerBuilder {
            $strategies = $this->tagged($app, $strategyTag, ReplacementStrategyInterface::class);
            $codecs = $this->tagged($app, $codecTag, PayloadCodecInterface::class);
            $transformations = $this->tagged($app, $transformationTag, ValueTransformationInterface::class);
            $extensions = $this->tagged($app, 'anonymizer.extension', AnonymizerExtensionInterface::class);
            $builder = AnonymizerBuilder::create()->withProfileProvider($app->make(ProfileCollection::class))
                ->withReplacementStrategies($strategies)->withPayloadCodecs($codecs)->withValueTransformations($transformations)
                ->withLimits($app->make(AnonymizationLimits::class));
            foreach ($extensions as $extension) {
                $builder = $builder->withExtension($extension);
            }

            return $builder;
        });
        $app->singleton(AnonymizerInterface::class, fn (Container $app): AnonymizerInterface => $app->make(AnonymizerBuilder::class)->build());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return iterable<T>
     */
    private function tagged(Container $app, string $tag, string $class): iterable
    {
        foreach ($app->tagged($tag) as $service) {
            yield Assert::isInstanceOf($service, $class, sprintf('Tagged service "%s" must implement %s.', $tag, $class));
        }
    }
}
// @codeCoverageIgnoreEnd
