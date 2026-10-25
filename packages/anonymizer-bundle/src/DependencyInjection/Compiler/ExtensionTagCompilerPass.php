<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webmozart\Assert\Assert;
use function Safe\preg_match;

// @internal
final class ExtensionTagCompilerPass implements CompilerPassInterface
{
    private const array TAGS = ['anonymizer.replacement_strategy', 'anonymizer.payload_codec', 'anonymizer.value_transformation'];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $this->validateModules($container);
        foreach (self::TAGS as $tag) {
            $this->validateTag($container, $tag);
        }
    }

    private function validateModules(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('anonymizer.extension') as $serviceId => $_tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();
            if (!is_string($class) || !is_a($class, AnonymizerExtensionInterface::class, true)) {
                throw new InvalidArgumentException(sprintf('Service "%s" uses tag "anonymizer.extension" without implementing %s.', $serviceId, AnonymizerExtensionInterface::class));
            }
        }
    }

    private function validateTag(ContainerBuilder $container, string $tag): void
    {
        $ids = [];
        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                Assert::isMap($attributes);
                $id = $attributes['id'] ?? null;
                if (!is_string($id) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $id) !== 1) {
                    throw new InvalidArgumentException(sprintf('Service "%s" uses tag "%s" without a valid id.', $serviceId, $tag));
                }
                if (isset($ids[$id])) {
                    throw new InvalidArgumentException(sprintf('Extension ID "%s" is registered more than once for tag "%s".', $id, $tag));
                }

                $ids[$id] = true;
            }
        }
    }
}
