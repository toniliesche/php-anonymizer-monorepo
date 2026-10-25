<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerLaravel;

// Coverage rationale: Laravel service-provider wiring is covered by Testbench integration tests.
// @codeCoverageIgnoreStart

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit\AuditRegistrar;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile\ProfileComposer;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Registration\CoreRegistrar;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Registration\ProfileRegistrar;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation\DateShiftTransformation;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizerAdapter;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Webmozart\Assert\Assert;

final class AnonymizerServiceProvider extends ServiceProvider
{
    private const string PROFILE_TAG = 'anonymizer.profile_provider';

    private const string STRATEGY_TAG = 'anonymizer.replacement_strategy';

    private const string CODEC_TAG = 'anonymizer.payload_codec';

    private const string TRANSFORMATION_TAG = 'anonymizer.value_transformation';

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/anonymizer.php', 'anonymizer');
        $this->app->singleton(ProfileComposer::class);
        $this->app->singleton(YamlPayloadCodec::class);
        $this->app->tag(YamlPayloadCodec::class, self::CODEC_TAG);
        $configuration = fn (string $key, mixed $default = null): mixed => $this->configuration($key, $default);
        new ProfileRegistrar()->register($this->app, $configuration, self::PROFILE_TAG);
        new CoreRegistrar()->register(
            $this->app,
            $configuration,
            fn (array $limits, string $key, int $default): int => $this->limit($limits, $key, $default),
            self::STRATEGY_TAG,
            self::CODEC_TAG,
            self::TRANSFORMATION_TAG,
        );
        $this->registerOptionalExtensions();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/anonymizer.php' => $this->app->basePath('config/anonymizer.php'),
            ], 'anonymizer-config');
        }

        // Resolve the public service eagerly so configuration and extension failures occur at boot.
        $this->app->make(AnonymizerInterface::class);
    }

    private function registerOptionalExtensions(): void
    {
        $this->registerFaker();
        $this->registerPersonas();
        $this->registerPseudonymization();
        new AuditRegistrar()->register($this->app, fn (string $key, mixed $default = null): mixed => $this->configuration($key, $default));
        $this->registerSerializer();
    }

    private function registerFaker(): void
    {
        if (!(bool) $this->configuration('anonymizer.faker.enabled', false)) {
            return;
        }
        $factory = FakerReplacementStrategyFactory::class;
        if (!class_exists($factory)) {
            throw new InvalidArgumentException('Faker integration is enabled but php-anonymizer/anonymizer-faker is not installed.');
        }
        $generator = 'anonymizer.faker.generator';
        $this->app->singleton($generator, fn (Container $app): object => $factory::createGenerator($this->stringConfiguration('anonymizer.faker.locale', 'en_US'), $this->configuration('anonymizer.faker.seed')));
        foreach ($factory::ids() as $id) {
            $service = 'anonymizer.faker.' . str_replace('.', '_', $id);
            $this->app->singleton($service, fn (Container $app) => $factory::createStrategy($app->make($generator), $id));
            $this->app->tag($service, self::STRATEGY_TAG);
        }
    }

    private function registerPersonas(): void
    {
        if (!(bool) $this->configuration('anonymizer.personas.enabled', false)) {
            return;
        }
        $factory = PersonaReplacementStrategyFactory::class;
        if (!class_exists($factory)) {
            throw new InvalidArgumentException('Personas integration is enabled but php-anonymizer/anonymizer-personas is not installed.');
        }
        foreach ($factory::IDS as $id) {
            $service = 'anonymizer.personas.' . str_replace('.', '_', $id);
            $this->app->singleton($service, fn () => $factory::createStrategy($this->stringConfiguration('anonymizer.personas.locale', 'en'), $id));
            $this->app->tag($service, self::STRATEGY_TAG);
        }
    }

    private function registerPseudonymization(): void
    {
        if (!(bool) $this->configuration('anonymizer.pseudonymization.enabled', false)) {
            return;
        }
        if (!class_exists(HmacPseudonymStrategy::class)) {
            throw new InvalidArgumentException('Pseudonymization is enabled but php-anonymizer/anonymizer-pseudonymization is not installed.');
        }
        $keyProvider = $this->configuration('anonymizer.pseudonymization.key_provider');
        if (!is_string($keyProvider) || $keyProvider === '') {
            throw new InvalidArgumentException('anonymizer.pseudonymization.key_provider is required when pseudonymization is enabled.');
        }
        $strategy = HmacPseudonymStrategy::class;
        $transformation = DateShiftTransformation::class;
        $this->app->singleton($strategy, function (Container $app) use ($keyProvider, $strategy): object {
            $keys = Assert::isInstanceOf($app->make($keyProvider), PseudonymKeyProviderInterface::class, sprintf('Pseudonym key provider "%s" must implement %s.', $keyProvider, PseudonymKeyProviderInterface::class));

            return new $strategy($keys);
        });
        $this->app->tag($strategy, self::STRATEGY_TAG);
        $this->app->singleton($transformation, function (Container $app) use ($keyProvider, $transformation): object {
            $keys = Assert::isInstanceOf($app->make($keyProvider), PseudonymKeyProviderInterface::class, sprintf('Pseudonym key provider "%s" must implement %s.', $keyProvider, PseudonymKeyProviderInterface::class));

            return new $transformation($keys);
        });
        $this->app->tag($transformation, self::TRANSFORMATION_TAG);
    }

    private function registerSerializer(): void
    {
        if (!$this->serializerEnabled()) {
            return;
        }
        if (!class_exists(SymfonyObjectAnonymizerAdapter::class)) {
            throw new InvalidArgumentException('Serializer integration is enabled but php-anonymizer/anonymizer-symfony-serializer is not installed.');
        }
        $service = $this->configuration('anonymizer.serializer.service');
        $service = is_string($service) && $service !== '' ? $service : SerializerInterface::class;
        $this->app->singleton(SymfonyObjectAnonymizerAdapter::class, function (Container $app) use ($service): SymfonyObjectAnonymizerAdapter {
            $serializer = $this->serializer($app, $service);

            return new SymfonyObjectAnonymizerAdapter($serializer);
        });
        $this->app->singleton(SymfonySerializerExtension::class, function (Container $app) use ($service): SymfonySerializerExtension {
            $serializer = $this->serializer($app, $service);

            return new SymfonySerializerExtension($serializer);
        });
        $this->app->tag(SymfonySerializerExtension::class, 'anonymizer.extension');
    }

    private function serializerEnabled(): bool
    {
        return (bool) $this->configuration('anonymizer.serializer.enabled', false);
    }

    private function serializer(Container $app, string $service): DenormalizerInterface&NormalizerInterface
    {
        $serializer = $app->make($service);
        $normalizer = NormalizerInterface::class;
        $denormalizer = DenormalizerInterface::class;
        if (!$serializer instanceof $normalizer || !$serializer instanceof $denormalizer) {
            throw new InvalidArgumentException(sprintf('Serializer service "%s" must implement both %s and %s.', $service, $normalizer, $denormalizer));
        }

        return $serializer;
    }

    private function configuration(string $key, mixed $default = null): mixed
    {
        $config = $this->app->make('config');
        if (!is_object($config) || !method_exists($config, 'get')) {
            throw new InvalidArgumentException('Laravel config repository is unavailable.');
        }

        return $config->get($key, $default);
    }

    private function stringConfiguration(string $key, string $default): string
    {
        $value = $this->configuration($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $limits
     */
    private function limit(array $limits, string $key, int $default): int
    {
        return isset($limits[$key]) && is_int($limits[$key]) ? $limits[$key] : $default;
    }
}
// @codeCoverageIgnoreEnd
