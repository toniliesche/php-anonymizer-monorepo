# PHP Anonymizer Symfony Serializer

The package provides the object support behind the Bundle's single
`PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface`.
Arrays continue to use the core engine; objects are normalized, anonymized,
and denormalized into a new instance through the application's Symfony
Serializer service. The source object is never populated or mutated.
