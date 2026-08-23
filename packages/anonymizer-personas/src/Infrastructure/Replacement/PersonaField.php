<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

// @internal
enum PersonaField: string
{
    case Name = 'name';
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case Email = 'email';
    case Username = 'username';
    case Company = 'company';
    case Phone = 'phone';
    case Street = 'street';
    case StreetNumber = 'street_number';
    case City = 'city';
    case Postcode = 'postcode';
    case Country = 'country';

    public function strategyId(): string
    {
        return 'persona.' . $this->value;
    }
}
