<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Integration;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final class TestProfileProvider implements ProfileProviderInterface
{
    /**
     * @return iterable<ProfileDefinition>
     */
    #[Override]
    public function profiles(): iterable
    {
        yield ProfileDefinition::fromArray('provided', [
            'mode' => 'redact',
            'rules' => [['field' => 'email', 'strategy' => 'custom']],
        ]);
    }
}
