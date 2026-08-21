<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

final readonly class Persona
{
    public function __construct(
        public string $name,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $username,
        public string $company,
        public string $phone,
        public string $street,
        public string $streetNumber,
        public string $city,
        public string $postcode,
        public string $country,
    ) {
    }

    public function value(PersonaField $field): string
    {
        return match ($field) {
            PersonaField::Name => $this->name,
            PersonaField::FirstName => $this->firstName,
            PersonaField::LastName => $this->lastName,
            PersonaField::Email => $this->email,
            PersonaField::Username => $this->username,
            PersonaField::Company => $this->company,
            PersonaField::Phone => $this->phone,
            PersonaField::Street => $this->street,
            PersonaField::StreetNumber => $this->streetNumber,
            PersonaField::City => $this->city,
            PersonaField::Postcode => $this->postcode,
            PersonaField::Country => $this->country,
        };
    }
}
