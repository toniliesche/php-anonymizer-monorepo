<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Tests\Support\Profile;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class TestProfileProvider implements ProfileProviderInterface
{
    #[Override]
    public function profiles(): iterable
    {
        yield ProfileDefinition::fromArray('tagged', [
            'mode' => 'redact',
            'rules' => [['field' => 'email']],
        ]);
    }
}
