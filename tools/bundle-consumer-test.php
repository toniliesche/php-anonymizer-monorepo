<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . '/php-anonymizer-bundle-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0o777, true);

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
            ['type' => 'path', 'url' => $root . '/packages/anonymizer', 'options' => ['symlink' => false]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-bundle', 'options' => ['symlink' => false]],
            ['type' => 'path', 'url' => $root . '/packages/anonymizer-symfony-serializer', 'options' => ['symlink' => false]],
        ],
        'minimum-stability' => 'dev',
    ];
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
        use Symfony\Component\Config\FileLocator;
        use Symfony\Component\Config\Loader\LoaderInterface;
        use Symfony\Component\DependencyInjection\ContainerBuilder;
        use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
        use Symfony\Component\HttpKernel\Kernel;

        require __DIR__ . '/vendor/autoload.php';

        final class ConsumerKernel extends Kernel
        {
            public function registerBundles(): iterable
            {
                yield new AnonymizerBundle();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->register('serializer', \Symfony\Component\Serializer\Serializer::class)
                        ->setArguments([[]]);
                    (new YamlFileLoader($container, new FileLocator(__DIR__)))->load('anonymizer.yaml');
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

        $kernel = new ConsumerKernel('test', false);
        $kernel->boot();
        $anonymizer = $kernel->getContainer()->get(AnonymizerInterface::class);
        if ($anonymizer->anonymize('consumer', ['email' => 'secret']) !== ['email' => '[REDACTED]']) {
            exit(1);
        }

        $kernel->shutdown();
        PHP);

    $command = sprintf('cd %s && composer install --no-interaction --no-progress --no-dev && php fixture.php', escapeshellarg($directory));
    passthru($command, $exitCode);

    exit($exitCode);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
