<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;

final readonly class PersonaReplacementStrategy implements ReplacementStrategyInterface
{
    public function __construct(private Persona $persona, private PersonaField $field)
    {
    }

    #[Override]
    public function id(): string
    {
        return $this->field->strategyId();
    }

    #[Override]
    public function replace(string $value): string
    {
        return $this->persona->value($this->field);
    }
}
