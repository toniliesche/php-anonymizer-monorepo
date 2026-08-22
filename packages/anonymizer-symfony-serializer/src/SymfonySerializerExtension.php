<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizerAdapter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Registers Symfony Serializer object anonymization with the core builder.
 *
 * @codeCoverageIgnore Container/module wiring.
 */
final readonly class SymfonySerializerExtension implements AnonymizerExtensionInterface
{
    public function __construct(private NormalizerInterface&DenormalizerInterface $serializer)
    {
    }

    #[Override]
    public function configure(AnonymizerBuilder $builder): AnonymizerBuilder
    {
        return $builder->withObjectAnonymizer(new SymfonyObjectAnonymizerAdapter($this->serializer));
    }
}
