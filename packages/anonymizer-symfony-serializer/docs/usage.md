# Symfony Serializer adapter

Install `php-anonymizer/anonymizer-symfony-serializer`; Composer installs the
required core package and Symfony Serializer 8.1.9 or later in the 8.1 line.
Provide an application-owned Serializer implementing both
`NormalizerInterface` and `DenormalizerInterface`. Register
`SymfonySerializerExtension` with the builder, or let the Bundle/Laravel
integration register it when explicitly enabled. Configure normalizers,
encoders, name converters, and groups for the application's data model; the
adapter forwards its `adapterOptions` as Serializer context.

Object anonymization creates a new instance by default and uses the generic
`AnonymizerInterface` façade. Pass the object in an `ObjectAnonymizationRequest`;
its optional `adapterOptions` are passed to both Symfony normalize and
denormalize calls. Since those options are caller-controlled, omit
`object_to_populate` when source immutability matters. The destination class
must be denormalizable by the configured Serializer.

See the [monorepo user guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/user-guide.md)
and [migration guide](https://github.com/toniliesche/php-anonymizer-monorepo/blob/main/docs/migration-v2.md).

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
