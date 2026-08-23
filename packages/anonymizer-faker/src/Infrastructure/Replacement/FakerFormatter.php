<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement;

// @internal
enum FakerFormatter: string
{
    case BuildingNumber = 'faker.building_number';
    case City = 'faker.city';
    case Company = 'faker.company';
    case Country = 'faker.country';
    case Email = 'faker.email';
    case FirstName = 'faker.first_name';
    case HouseNumber = 'faker.house_number';
    case LastName = 'faker.last_name';
    case Name = 'faker.name';
    case Password = 'faker.password';
    case Postcode = 'faker.postcode';
    case Street = 'faker.street';
    case StreetNumber = 'faker.street_number';
    case Username = 'faker.username';
    case Zip = 'faker.zip';

    public function formatter(): string
    {
        return match ($this) {
            self::BuildingNumber, self::HouseNumber, self::StreetNumber => 'buildingNumber',
            self::City => 'city', self::Company => 'company', self::Country => 'country',
            self::Email => 'safeEmail', self::FirstName => 'firstName', self::LastName => 'lastName',
            self::Name => 'name', self::Password => 'password', self::Postcode, self::Zip => 'postcode',
            self::Street => 'streetName', self::Username => 'userName',
        };
    }

    public function id(): string
    {
        return $this->value;
    }
}
