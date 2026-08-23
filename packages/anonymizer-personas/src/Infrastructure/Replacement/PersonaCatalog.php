<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement;

// @internal
final class PersonaCatalog
{
    public static function for(PersonaLocale $locale): Persona
    {
        return match ($locale) {
            PersonaLocale::English => new Persona('Joe Bloggs', 'Joe', 'Bloggs', 'joe.bloggs@example.invalid', 'joe.bloggs', 'Bloggs Ltd', '+44 20 7946 0000', 'Example Street', '1', 'London', 'SW1A 1AA', 'United Kingdom'),
            PersonaLocale::German => new Persona('Max Mustermann', 'Max', 'Mustermann', 'max.mustermann@example.invalid', 'max.mustermann', 'Muster GmbH', '+49 30 000000', 'Musterstraße', '1', 'Berlin', '10115', 'Germany'),
            PersonaLocale::Spanish => new Persona('Juan García', 'Juan', 'García', 'juan.garcia@example.invalid', 'juan.garcia', 'García S.L.', '+34 910 000 000', 'Calle de Ejemplo', '1', 'Madrid', '28001', 'Spain'),
            PersonaLocale::French => new Persona('Jean Dupont', 'Jean', 'Dupont', 'jean.dupont@example.invalid', 'jean.dupont', 'Dupont SARL', '+33 1 00 00 00 00', 'Rue de l’Exemple', '1', 'Paris', '75001', 'France'),
            PersonaLocale::Italian => new Persona('Mario Rossi', 'Mario', 'Rossi', 'mario.rossi@example.invalid', 'mario.rossi', 'Rossi S.r.l.', '+39 06 00000000', 'Via dell’Esempio', '1', 'Roma', '00100', 'Italy'),
            PersonaLocale::Dutch => new Persona('Jan Jansen', 'Jan', 'Jansen', 'jan.jansen@example.invalid', 'jan.jansen', 'Jansen B.V.', '+31 20 000 0000', 'Voorbeeldstraat', '1', 'Amsterdam', '1011 AA', 'Netherlands'),
            PersonaLocale::Portuguese => new Persona('António Silva', 'António', 'Silva', 'antonio.silva@example.invalid', 'antonio.silva', 'Silva Lda.', '+351 21 000 0000', 'Rua do Exemplo', '1', 'Lisboa', '1000-001', 'Portugal'),
            PersonaLocale::Irish => new Persona('Seán Murphy', 'Seán', 'Murphy', 'sean.murphy@example.invalid', 'sean.murphy', 'Murphy Limited', '+353 1 000 0000', 'Example Road', '1', 'Dublin', 'D01 A1A1', 'Ireland'),
            PersonaLocale::Austrian => new Persona('Franz Huber', 'Franz', 'Huber', 'franz.huber@example.invalid', 'franz.huber', 'Huber GmbH', '+43 1 000000', 'Musterstraße', '1', 'Wien', '1010', 'Austria'),
            PersonaLocale::Swiss => new Persona('Hans Müller', 'Hans', 'Müller', 'hans.mueller@example.invalid', 'hans.mueller', 'Müller AG', '+41 44 000 00 00', 'Beispielstrasse', '1', 'Zürich', '8001', 'Switzerland'),
            PersonaLocale::BelgianDutch => new Persona('Jan Peeters', 'Jan', 'Peeters', 'jan.peeters@example.invalid', 'jan.peeters', 'Peeters BV', '+32 2 000 00 00', 'Voorbeeldstraat', '1', 'Antwerpen', '2000', 'Belgium'),
        };
    }
}
