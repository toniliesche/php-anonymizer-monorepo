<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Tests\Support\Serializer;

final class AdapterPerson
{
    public function __construct(public string $name)
    {
    }
}
