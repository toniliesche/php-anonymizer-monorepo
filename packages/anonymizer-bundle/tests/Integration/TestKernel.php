<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Integration;

use Override;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\Tests\Support\Extension\TestPayloadCodec;
use PhpAnonymizer\AnonymizerBundle\Tests\Support\Extension\TestProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Tests\Support\Extension\TestReplacementStrategy;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use function Safe\getmypid;

final class TestKernel extends Kernel
{
    /**
     * @return iterable<BundleInterface>
     */
    #[Override]
    // @phpstan-ignore return.deprecatedInterface (Symfony 8.1 marks the bundle interface as deprecated.)
    public function registerBundles(): iterable
    {
        yield new AnonymizerBundle();
    }

    #[Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->register('object_normalizer', ObjectNormalizer::class);
            $container->register('serializer', Serializer::class)
                ->setArguments([[new Reference('object_normalizer')]]);
            $yamlLoader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
            $yamlLoader->load('anonymizer.yaml');

            $container->register(TestPayloadCodec::class)
                ->addTag('anonymizer.payload_codec', ['id' => 'pipe']);
            $container->register(TestProfileProvider::class)
                ->addTag('anonymizer.profile_provider');
            $container->register(TestReplacementStrategy::class)
                ->addTag('anonymizer.replacement_strategy', ['id' => 'custom']);
        });
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/php-anonymizer-bundle-test-' . getmypid();
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }
}
