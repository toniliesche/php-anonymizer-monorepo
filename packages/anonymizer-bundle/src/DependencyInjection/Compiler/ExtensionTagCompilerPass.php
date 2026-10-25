<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Safe\preg_match;

final class ExtensionTagCompilerPass implements CompilerPassInterface
{
    private const array TAGS = [
        'anonymizer.replacement_strategy' => ['redact', 'star_mask', 'pseudonymize', 'faker.building_number', 'faker.city', 'faker.company', 'faker.country', 'faker.email', 'faker.first_name', 'faker.house_number', 'faker.last_name', 'faker.name', 'faker.password', 'faker.postcode', 'faker.street', 'faker.street_number', 'faker.username', 'faker.zip', 'persona.name', 'persona.first_name', 'persona.last_name', 'persona.email', 'persona.username', 'persona.company', 'persona.phone', 'persona.street', 'persona.street_number', 'persona.city', 'persona.postcode', 'persona.country'],
        'anonymizer.payload_codec' => ['json', 'yaml'],
        'anonymizer.value_transformation' => ['nullify', 'partial_mask', 'date_generalize', 'numeric_bucket', 'date_shift'],
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (self::TAGS as $tag => $builtIns) {
            $this->validateTag($container, $tag, $builtIns);
        }
    }

    /**
     * @param list<string> $builtIns
     */
    private function validateTag(ContainerBuilder $container, string $tag, array $builtIns): void
    {
        $ids = [];
        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                /** @var array<string, mixed> $attributes */
                if (($attributes['builtin'] ?? false) === true) {
                    continue;
                }
                $id = $attributes['id'] ?? null;
                if (!is_string($id) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $id) !== 1) {
                    throw new InvalidArgumentException(sprintf('Service "%s" uses tag "%s" without a valid id.', $serviceId, $tag));
                }
                if (in_array($id, $builtIns, true) || isset($ids[$id])) {
                    throw new InvalidArgumentException(sprintf('Extension ID "%s" is registered more than once for tag "%s".', $id, $tag));
                }

                $ids[$id] = true;
            }
        }
    }
}
