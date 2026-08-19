<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle;

use Override;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AnonymizerBundle extends Bundle
{
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ExtensionTagCompilerPass());
        $container->addCompilerPass(new SerializerServiceCompilerPass());
    }
}
