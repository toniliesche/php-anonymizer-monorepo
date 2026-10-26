<?php

declare(strict_types=1);

$root = dirname(__DIR__);

runBundleConsumer($root, false);
runBundleConsumer($root, true);

echo "Bundle consumers passed with and without the optional Serializer adapter.\n";

function runBundleConsumer(string $root, bool $withSerializerAdapter): void
{
    $directory = sys_get_temp_dir() . '/php-anonymizer-bundle-consumer-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0o700)) {
        throw new RuntimeException('Could not create Bundle consumer directory.');
    }

    try {
        $composer = [
            'name' => 'php-anonymizer/bundle-consumer-fixture',
            'require' => [
                'php' => '^8.5',
                'php-anonymizer/anonymizer-bundle' => '@dev',
                'symfony/framework-bundle' => '^8.0',
                'symfony/yaml' => '^8.0',
            ],
            'repositories' => [
                ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false, 'versions' => ['php-anonymizer/anonymizer' => '2.0.x-dev']]],
                ['type' => 'path', 'url' => $root . '/packages/anonymizer-bundle', 'options' => ['symlink' => false]],
            ],
            'minimum-stability' => 'dev',
        ];
        if ($withSerializerAdapter) {
            $composer['require']['php-anonymizer/anonymizer-symfony-serializer'] = '@dev';
            $composer['repositories'][] = [
                'type' => 'path',
                'url' => $root . '/packages/anonymizer-symfony-serializer',
                'options' => ['symlink' => false, 'versions' => ['php-anonymizer/anonymizer-symfony-serializer' => '2.0.x-dev']],
            ];
        }
        file_put_contents($directory . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents($directory . '/anonymizer.yaml', <<<'YAML'
            anonymizer:
              profiles:
                consumer:
                  mode: redact
                  rules:
                    - field: email
            YAML);
        file_put_contents($directory . '/fixture.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
            use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
            use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
            use Symfony\Component\Config\Loader\LoaderInterface;
            use Symfony\Component\DependencyInjection\ContainerBuilder;
            use Symfony\Component\DependencyInjection\Reference;
            use Symfony\Component\HttpKernel\Kernel;
            use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
            use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
            use Symfony\Component\Serializer\Serializer;

            require __DIR__ . '/vendor/autoload.php';

            final class ConsumerUser
            {
                public string $email = '';
            }

            final class ConsumerUserNormalizer implements NormalizerInterface, DenormalizerInterface
            {
                public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|ArrayObject|null
                {
                    if (!$data instanceof ConsumerUser) {
                        throw new RuntimeException('Unsupported consumer object.');
                    }

                    return ['email' => $data->email];
                }

                public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
                {
                    return $data instanceof ConsumerUser;
                }

                public function getSupportedTypes(?string $format): array
                {
                    return [ConsumerUser::class => true];
                }

                public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
                {
                    if ($type !== ConsumerUser::class || !is_array($data) || !is_string($data['email'] ?? null)) {
                        throw new RuntimeException('Invalid consumer object data.');
                    }
                    $user = new ConsumerUser();
                    $user->email = $data['email'];

                    return $user;
                }

                public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
                {
                    return $type === ConsumerUser::class;
                }

            }

            final class ConsumerKernel extends Kernel
            {
                public function registerBundles(): iterable
                {
                    yield new AnonymizerBundle();
                }

                public function registerContainerConfiguration(LoaderInterface $loader): void
                {
                    $loader->load(static function (ContainerBuilder $container): void {
                        $scenario = $GLOBALS['consumerScenario'];
                        if ($scenario === 'enabled') {
                            $container->register('consumer.normalizer', ConsumerUserNormalizer::class);
                            $container->register('serializer', Serializer::class)
                                ->setArguments([[new Reference('consumer.normalizer')]]);
                        }
                        $config = ['profiles' => ['consumer' => [
                            'mode' => 'redact',
                            'rules' => [['field' => 'email']],
                        ]]];
                        if ($scenario !== 'disabled') {
                            $config['serializer'] = ['enabled' => true];
                        }
                        $container->loadFromExtension('anonymizer', $config);
                    });
                }

                public function getCacheDir(): string
                {
                    return sys_get_temp_dir() . '/php-anonymizer-consumer-cache-' . getmypid();
                }

                public function getLogDir(): string
                {
                    return $this->getCacheDir();
                }
            }

            $scenario = $argv[1] ?? '';
            $GLOBALS['consumerScenario'] = $scenario;
            $kernel = new ConsumerKernel('test', false);
            try {
                $kernel->boot();
            } catch (InvalidConfigurationException $exception) {
                if ($scenario === 'missing-adapter'
                    && str_contains($exception->getMessage(), 'php-anonymizer/anonymizer-symfony-serializer is not installed')
                ) {
                    exit(0);
                }

                throw $exception;
            }
            if ($scenario === 'missing-adapter') {
                throw new RuntimeException('Enabling the missing Serializer adapter unexpectedly succeeded.');
            }

            $anonymizer = $kernel->getContainer()->get(AnonymizerInterface::class);
            if ($anonymizer->anonymize('consumer', ['email' => 'secret']) !== ['email' => '[REDACTED]']) {
                throw new RuntimeException('Bundle did not anonymize the consumer array.');
            }
            if ($scenario === 'enabled') {
                $user = new ConsumerUser();
                $user->email = 'secret';
                $result = $anonymizer->anonymize('consumer', $user);
                if (!$result instanceof ConsumerUser || $result->email !== '[REDACTED]') {
                    throw new RuntimeException('Optional Serializer adapter did not anonymize the consumer object.');
                }
            }
            $kernel->shutdown();
            PHP);

        runProcess(['composer', 'install', '--no-interaction', '--no-progress', '--no-dev'], $directory);
        if ($withSerializerAdapter) {
            runProcess([PHP_BINARY, 'fixture.php', 'enabled'], $directory);

            return;
        }

        runProcess([PHP_BINARY, 'fixture.php', 'disabled'], $directory);
        runProcess([PHP_BINARY, 'fixture.php', 'missing-adapter'], $directory);
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}

/**
 * @param list<string> $command
 */
function runProcess(array $command, string $directory): void
{
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ], $pipes, $directory);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start consumer command: ' . $command[0]);
    }
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Consumer command failed: ' . implode(' ', $command));
    }
}
