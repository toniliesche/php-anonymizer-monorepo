<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

final class LegacyRuleValueConverter
{
    private const array FAKER_FIELDS = ['buildingNumber' => 'faker.building_number', 'city' => 'faker.city', 'company' => 'faker.company', 'country' => 'faker.country', 'email' => 'faker.email', 'firstName' => 'faker.first_name', 'houseNumber' => 'faker.house_number', 'lastName' => 'faker.last_name', 'name' => 'faker.name', 'password' => 'faker.password', 'postcode' => 'faker.postcode', 'street' => 'faker.street', 'streetNumber' => 'faker.street_number', 'username' => 'faker.username', 'zip' => 'faker.zip'];

    public function strategy(mixed $value): string
    {
        return is_string($value) && isset(self::FAKER_FIELDS[$value]) ? self::FAKER_FIELDS[$value] : 'redact';
    }
    public function fallback(mixed $value): mixed
    {
        return is_array($value) ? ($value['value_type'] ?? null) : null;
    }
}
