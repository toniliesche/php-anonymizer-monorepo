# Symfony Serializer adapter

Install the adapter alongside the core and provide a Symfony Serializer that
implements both normalization and denormalization. Register
`SymfonySerializerExtension` with the builder or let the Bundle/Laravel
integration register it when explicitly enabled.

Object anonymization creates a new instance, preserves the source object, and
uses the generic `AnonymizerInterface` façade. The adapter rejects
`object_to_populate` to protect source immutability.

See the repository [`user guide`](../../../docs/user-guide.md) and
[`migration guide`](../../../docs/migration-v2.md).

The adapter is registered as an extension and returns a new object:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class Person { public function __construct(public string $name) {} }
$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('person', ['mode' => 'redact', 'rules' => [['field' => 'name']]]))
    ->withExtension(new SymfonySerializerExtension(new Serializer([new ObjectNormalizer()])))
    ->build();
$source = new Person('Ada');
$safe = $anonymizer->anonymizeObject(new ObjectAnonymizationRequest('person', $source));

if (!$safe instanceof Person || $safe->name !== '[REDACTED]' || $source->name !== 'Ada') {
    throw new RuntimeException('The Serializer object example failed.');
}
```
