<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerLaravel;

// @codeCoverageIgnoreStart
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspector;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ValueTransformationInterface;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit\AuditLoggerAdapter;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile\ProfileComposer;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation\DateShiftTransformation;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyAnonymizer;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizerAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

// @phpstan-ignore complexity.classLike (framework registrar extraction is scheduled for Iteration 20)
final class AnonymizerServiceProvider extends ServiceProvider
{
    private const string PROFILE_TAG = 'anonymizer.profile_provider';

    private const string STRATEGY_TAG = 'anonymizer.replacement_strategy';

    private const string CODEC_TAG = 'anonymizer.payload_codec';

    private const string TRANSFORMATION_TAG = 'anonymizer.value_transformation';

    /** @var list<string> */
    private const array FAKER_IDS = ['faker.building_number', 'faker.city', 'faker.company', 'faker.country', 'faker.email', 'faker.first_name', 'faker.house_number', 'faker.last_name', 'faker.name', 'faker.password', 'faker.postcode', 'faker.street', 'faker.street_number', 'faker.username', 'faker.zip'];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/anonymizer.php', 'anonymizer');
        $this->app->singleton(ProfileComposer::class);
        $this->app->singleton(YamlPayloadCodec::class);
        $this->app->tag(YamlPayloadCodec::class, self::CODEC_TAG);
        $this->registerProfiles();
        $this->registerCore();
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

    private function registerProfiles(): void
    {
        $this->app->singleton(ProfileCollection::class, function (Container $app): ProfileCollection {
            $configured = $this->configuration('anonymizer.profiles', []);
            if (!is_array($configured)) {
                throw new InvalidArgumentException('anonymizer.profiles must be a map.');
            }

            /** @var array<string, mixed> $configured */
            return $app->make(ProfileComposer::class)->compose($configured, $app->tagged(self::PROFILE_TAG));
        });
        $this->app->singleton(ProfileInspector::class, fn (Container $app): ProfileInspector => new ProfileInspector($app->make(ProfileCollection::class)));
        $this->app->alias(ProfileInspector::class, ProfileInspectorInterface::class);
    }

    private function registerCore(): void
    {
        $this->app->singleton(AnonymizationLimits::class, function (): AnonymizationLimits {
            $limits = $this->configuration('anonymizer.limits', []);
            if (!is_array($limits)) {
                throw new InvalidArgumentException('anonymizer.limits must be a map.');
            }

            /** @var array<string, mixed> $limits */
            return new AnonymizationLimits(
                $this->limit($limits, 'max_input_bytes', 10_485_760),
                $this->limit($limits, 'max_depth', 64),
                $this->limit($limits, 'max_visited_nodes', 100_000),
                $this->limit($limits, 'max_collection_entries', 50_000),
                $this->limit($limits, 'max_embedded_payload_depth', 8),
            );
        });
        $this->app->singleton(ArrayAnonymizer::class, function (Container $app): ArrayAnonymizer {
            /** @var iterable<ReplacementStrategyInterface> $strategies */
            $strategies = $app->tagged(self::STRATEGY_TAG);

            /** @var iterable<PayloadCodecInterface> $codecs */
            $codecs = $app->tagged(self::CODEC_TAG);

            /** @var iterable<ValueTransformationInterface> $transformations */
            $transformations = $app->tagged(self::TRANSFORMATION_TAG);

            return new ArrayAnonymizer($app->make(ProfileCollection::class), $strategies, $codecs, $app->make(AnonymizationLimits::class), $transformations);
        });
        $this->registerBuilder();
        $this->app->singleton(AnonymizerInterface::class, fn (Container $app): AnonymizerInterface => $app->make(AnonymizerBuilder::class)->build());
    }

    private function registerBuilder(): void
    {
        $this->app->singleton(AnonymizerBuilder::class, function (Container $app): AnonymizerBuilder {
            /** @var iterable<ReplacementStrategyInterface> $strategies */
            $strategies = $app->tagged(self::STRATEGY_TAG);

            /** @var iterable<PayloadCodecInterface> $codecs */
            $codecs = $app->tagged(self::CODEC_TAG);

            /** @var iterable<ValueTransformationInterface> $transformations */
            $transformations = $app->tagged(self::TRANSFORMATION_TAG);

            $builder = AnonymizerBuilder::create()
                ->withProfileProvider($app->make(ProfileCollection::class))
                ->withReplacementStrategies($strategies)
                ->withPayloadCodecs($codecs)
                ->withValueTransformations($transformations)
                ->withLimits($app->make(AnonymizationLimits::class));

            if ($this->serializerEnabled() && class_exists(SymfonyObjectAnonymizerAdapter::class)) {
                return $builder->withObjectAnonymizer($app->make(SymfonyObjectAnonymizerAdapter::class));
            }

            return $builder;
        });
    }

    private function registerOptionalExtensions(): void
    {
        $this->registerFaker();
        $this->registerPersonas();
        $this->registerPseudonymization();
        $this->registerAudit();
        $this->registerSerializer();
    }

    /**
     * @codeCoverageIgnoreStart
     */
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
        foreach (self::FAKER_IDS as $id) {
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
            /** @var PseudonymKeyProviderInterface $keys */
            $keys = $app->make($keyProvider);

            return new $strategy($keys);
        });
        $this->app->tag($strategy, self::STRATEGY_TAG);
        $this->app->singleton($transformation, function (Container $app) use ($keyProvider, $transformation): object {
            /** @var PseudonymKeyProviderInterface $keys */
            $keys = $app->make($keyProvider);

            return new $transformation($keys);
        });
        $this->app->tag($transformation, self::TRANSFORMATION_TAG);
    }

    private function registerAudit(): void
    {
        if (!(bool) $this->configuration('anonymizer.audit.enabled', false)) {
            return;
        }
        $logger = $this->configuration('anonymizer.audit.logger_service');
        if (!is_string($logger) || $logger === '') {
            throw new InvalidArgumentException('anonymizer.audit.logger_service is required when audit is enabled.');
        }
        $this->app->singleton(AuditLoggerAdapter::class, function (Container $app) use ($logger): AuditLoggerAdapter {
            /** @var LoggerInterface $loggerInstance */
            $loggerInstance = $app->make($logger);

            return new AuditLoggerAdapter($app->make(ArrayAnonymizer::class), $loggerInstance);
        });
    }

    private function registerSerializer(): void
    {
        if (!$this->serializerEnabled()) {
            return;
        }
        if (!class_exists(SymfonyObjectAnonymizer::class)) {
            throw new InvalidArgumentException('Serializer integration is enabled but php-anonymizer/anonymizer-symfony-serializer is not installed.');
        }
        $service = $this->configuration('anonymizer.serializer.service');
        $service = is_string($service) && $service !== '' ? $service : SerializerInterface::class;
        $objectAnonymizer = SymfonyObjectAnonymizer::class;
        $anonymizer = SymfonyAnonymizer::class;
        $this->app->singleton(SymfonyObjectAnonymizerAdapter::class, function (Container $app) use ($service): SymfonyObjectAnonymizerAdapter {
            /** @var DenormalizerInterface&NormalizerInterface $serializer */
            $serializer = $this->serializer($app, $service);

            return new SymfonyObjectAnonymizerAdapter($serializer);
        });
        $this->app->singleton($objectAnonymizer, function (Container $app) use ($objectAnonymizer, $service): object {
            /** @var DenormalizerInterface&NormalizerInterface $serializer */
            $serializer = $this->serializer($app, $service);

            return new $objectAnonymizer($app->make(ArrayAnonymizer::class), $serializer);
        });
        $this->app->singleton($anonymizer, fn (Container $app): SymfonyAnonymizer => new $anonymizer($app->make(ArrayAnonymizer::class), $app->make($objectAnonymizer)));
    }

    private function serializerEnabled(): bool
    {
        return (bool) $this->configuration('anonymizer.serializer.enabled', false);
    }

    private function serializer(Container $app, string $service): object
    {
        $serializer = $app->make($service);
        $normalizer = NormalizerInterface::class;
        $denormalizer = DenormalizerInterface::class;
        if (!$serializer instanceof $normalizer || !$serializer instanceof $denormalizer) {
            throw new InvalidArgumentException(sprintf('Serializer service "%s" must implement both %s and %s.', $service, $normalizer, $denormalizer));
        }

        /** @var DenormalizerInterface&NormalizerInterface $serializer */
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

    /** @codeCoverageIgnoreEnd */
}
// @codeCoverageIgnoreEnd
