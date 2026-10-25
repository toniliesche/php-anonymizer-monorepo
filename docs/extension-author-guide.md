# Extension author guide

PHP Anonymizer extensions implement framework-independent PHP interfaces.
Applications register them with the immutable `AnonymizerBuilder`; Symfony and
Laravel can register the same objects through documented service tags. The
core does not scan the filesystem or a container for profiles or extensions.

Install the core package and use PHP 8.5 or newer. Implement only the ports
needed by your integration. Public extension contracts are listed in the
[2.0 API reference](api-surface-v2.md).

## Profile providers

Implement `PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface`
to return immutable `ProfileDefinition` values:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class CustomerProfiles implements ProfileProviderInterface
{
    public function profiles(): iterable
    {
        yield ProfileDefinition::fromArray('customer', [
            'mode' => 'redact',
            'rules' => [['field' => 'email']],
        ]);
    }
}

$anonymizer = AnonymizerBuilder::create()
    ->withProfileProvider(new CustomerProfiles())
    ->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'ada@example.test']);

if ($safe !== ['email' => '[REDACTED]']) {
    throw new RuntimeException('The profile provider example failed.');
}
```

`profiles()` is called during each `build()`. Return the same definitions for
the same provider configuration; do not load request data, expose secrets, or
make the result depend on call order. The builder materializes profile
definitions returned by the provider when it builds the façade.

## Replacement strategies

Implement `PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface`
for a string-to-string replacement. IDs are stable, case-sensitive strings
matching `^[A-Za-z][A-Za-z0-9_.-]*$`.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class TokenMask implements ReplacementStrategyInterface
{
    public function id(): string
    {
        return 'acme.token_mask';
    }

    public function replace(string $value): string
    {
        return '[' . str_repeat('*', mb_strlen($value, 'UTF-8')) . ']';
    }
}

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email', 'strategy' => 'acme.token_mask']],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfile($profile)
    ->withReplacementStrategy(new TokenMask())
    ->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'ada@example.test']);

if ($safe['email'] !== '[' . str_repeat('*', mb_strlen('ada@example.test', 'UTF-8')) . ']') {
    throw new RuntimeException('The replacement strategy example failed.');
}
```

For request-specific behavior, implement
`ContextualReplacementStrategyInterface`. It extends the simple strategy
contract and receives `ReplacementContext` with the profile, JSON Pointer,
non-secret `key_id`, and application attributes. The simple `replace()` method
is still required; contextual calls use `replaceWithContext()`.

<!-- executable -->
```php
use LogicException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ContextualReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ReplacementContext;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class TenantMask implements ContextualReplacementStrategyInterface
{
    public function id(): string
    {
        return 'acme.tenant_mask';
    }

    public function replace(string $value): string
    {
        throw new LogicException('TenantMask requires contextual anonymization.');
    }

    public function replaceWithContext(string $value, ReplacementContext $context): string
    {
        $scope = isset($context->attributes()['tenant']) ? 'tenant' : 'default';

        return '[' . $scope . ':' . str_repeat('*', mb_strlen($value, 'UTF-8')) . ']';
    }
}

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email', 'strategy' => 'acme.tenant_mask', 'key_id' => 'customer-v1']],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfile($profile)
    ->withReplacementStrategy(new TenantMask())
    ->build();
$safe = $anonymizer->anonymize(
    'customer',
    ['email' => 'secret'],
    new AnonymizationContext(['tenant' => 'acme']),
);

if ($safe['email'] !== '[tenant:******]') {
    throw new RuntimeException('The contextual strategy example failed.');
}
```

The sample only demonstrates context delivery; it is not a pseudonymization
scheme. Do not use a key ID as key material. Contextual strategies require a
`key_id` in the rule, and the application must pass an
`AnonymizationContext` when the strategy needs attributes.

## Payload and document codecs

Implement `PayloadCodecInterface` to encode and decode associative maps. Use
`DocumentPayloadCodecInterface` when the codec can also handle explicit map or
list roots. A document codec implements all four methods: `id()`, `decode()`,
`encode()`, `decodeDocument()`, and `encodeDocument()`.

<!-- executable -->
```php
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\DocumentPayloadCodecInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class JsonDocumentCodec implements DocumentPayloadCodecInterface
{
    public function id(): string
    {
        return 'acme.json';
    }

    public function decode(string $payload): array
    {
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('Expected a JSON object map.');
        }

        return $data;
    }

    public function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function decodeDocument(string $payload): array
    {
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Expected a JSON object or array document.');
        }

        return $data;
    }

    public function encodeDocument(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}

$mapProfile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]);
$listProfile = ProfileDefinition::fromArray('records', [
    'mode' => 'redact',
    'root' => 'list',
    'rules' => [['field' => 'email']],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfiles([$mapProfile, $listProfile])
    ->withPayloadCodec(new JsonDocumentCodec())
    ->build();
$map = $anonymizer->anonymizePayload('customer', 'acme.json', '{"email":"ada@example.test"}');
$list = $anonymizer->anonymizeDocument('records', 'acme.json', '[{"email":"ada@example.test"}]');

if ($map !== '{"email":"[REDACTED]"}' || $list !== '[{"email":"[REDACTED]"}]') {
    throw new RuntimeException('The document codec example failed.');
}
```

Codec IDs follow the same stable-ID and uniqueness rules as strategy IDs.
Throw an exception when decoding or encoding fails; the public operation
reports a `PayloadCodecFailure` with operation and path context. Never include
payload contents in exception messages.

## Value transformations

Implement `ValueTransformationInterface` for a typed leaf operation. Declare
the input names in `supportedTypes()`, the result type in `outputType()`, and
validate every option in `validateOptions()`. The builder calls option
validation during `build()`; `transform()` receives the selected runtime value
when the rule runs.

<!-- executable -->
```php
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ValueTransformationInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class CapNumber implements ValueTransformationInterface
{
    public function id(): string
    {
        return 'acme.cap_number';
    }

    public function supportedTypes(): array
    {
        return ['int'];
    }

    public function outputType(): string
    {
        return 'int';
    }

    public function validateOptions(array $options): void
    {
        if (array_keys($options) !== ['maximum'] || !is_int($options['maximum']) || $options['maximum'] < 0) {
            throw new InvalidArgumentException('CapNumber requires a non-negative integer maximum.');
        }
    }

    public function transform(mixed $value, array $options): mixed
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException('CapNumber accepts integers only.');
        }

        return min($value, $options['maximum']);
    }
}

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'age', 'transform' => [
        'type' => 'acme.cap_number',
        'options' => ['maximum' => 99],
    ]]],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfile($profile)
    ->withValueTransformation(new CapNumber())
    ->build();
$safe = $anonymizer->anonymize('customer', ['age' => 120]);

if ($safe !== ['age' => 99]) {
    throw new RuntimeException('The value transformation example failed.');
}
```

For contextual transformations, implement
`ContextualValueTransformationInterface` and provide
`transformWithContext()`. It receives `ValueTransformationContext` with the
profile, path, optional rule `key_id`, and application attributes. Keep
`transform()` implemented for the base contract; reject calls that lack
required context instead of silently using a default.

<!-- executable -->
```php
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ValueTransformationContext;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ContextualValueTransformationInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class RegionLabel implements ContextualValueTransformationInterface
{
    public function id(): string
    {
        return 'acme.region_label';
    }

    public function supportedTypes(): array
    {
        return ['string'];
    }

    public function outputType(): string
    {
        return 'string';
    }

    public function validateOptions(array $options): void
    {
        if ($options !== []) {
            throw new InvalidArgumentException('RegionLabel does not accept options.');
        }
    }

    public function transform(mixed $value, array $options): mixed
    {
        throw new InvalidArgumentException('RegionLabel requires contextual anonymization.');
    }

    public function transformWithContext(mixed $value, array $options, ValueTransformationContext $context): mixed
    {
        if (!is_string($value) || !isset($context->attributes()['region'])) {
            throw new InvalidArgumentException('A string value and region context are required.');
        }

        return '[' . $context->attributes()['region'] . ']' . $value;
    }
}

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email', 'transform' => [
        'type' => 'acme.region_label',
        'options' => [],
    ]]],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfile($profile)
    ->withValueTransformation(new RegionLabel())
    ->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'ada@example.test'], new AnonymizationContext(['region' => 'EU']));

if ($safe !== ['email' => '[EU]ada@example.test']) {
    throw new RuntimeException('The contextual transformation example failed.');
}
```

## Object adapters

Implement `PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface`
to adapt application objects. Its `anonymize()` method receives the immutable
`ObjectAnonymizationRequest` and the generic `AnonymizerInterface`. Return an
`AnonymizationResultInterface` whose `data()` is a new object of the same
runtime class. The façade enforces that class requirement. Adapter options are
opaque to the core and are available through `adapterOptions()`.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final class Customer
{
    public function __construct(public string $name)
    {
    }
}

final readonly class CustomerResult implements AnonymizationResultInterface
{
    public function __construct(private Customer $customer, private array $paths)
    {
    }

    public function data(): array|object
    {
        return $this->customer;
    }

    public function anonymizedPaths(): array
    {
        return $this->paths;
    }

    public function missingPaths(): array
    {
        return [];
    }
}

final readonly class CustomerAdapter implements ObjectAnonymizerInterface
{
    public function anonymize(ObjectAnonymizationRequest $request, AnonymizerInterface $anonymizer): AnonymizationResultInterface
    {
        $source = $request->object();
        if (!$source instanceof Customer) {
            throw new InvalidArgumentException('CustomerAdapter only accepts Customer objects.');
        }

        $result = $anonymizer->anonymizeWithReport($request->profile(), ['name' => $source->name], $request->context());

        return new CustomerResult(new Customer($result->data()['name']), $result->anonymizedPaths());
    }
}

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'name']],
]);
$anonymizer = AnonymizerBuilder::create()
    ->withProfile($profile)
    ->withObjectAnonymizer(new CustomerAdapter())
    ->build();
$source = new Customer('Ada');
$safe = $anonymizer->anonymizeObject(new ObjectAnonymizationRequest('customer', $source));

if (!$safe instanceof Customer || $safe->name !== '[REDACTED]' || $source->name !== 'Ada') {
    throw new RuntimeException('The object adapter example failed.');
}
```

Adapters must not mutate the source object. Return the same runtime class and
preserve report paths and missing paths when returning a report. For a Symfony
Serializer implementation, see the
[Serializer package guide](../packages/anonymizer-symfony-serializer/docs/usage.md).

## Extension modules

Use `AnonymizerExtensionInterface` to group related registrations. Its
`configure()` method receives a builder and returns the configured builder; it
must not build a façade itself.

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class ModuleMask implements ReplacementStrategyInterface
{
    public function id(): string
    {
        return 'acme.module_mask';
    }

    public function replace(string $value): string
    {
        return str_repeat('*', mb_strlen($value, 'UTF-8'));
    }
}

final readonly class CustomerExtension implements AnonymizerExtensionInterface
{
    public function configure(AnonymizerBuilder $builder): AnonymizerBuilder
    {
        return $builder
            ->withProfile(ProfileDefinition::fromArray('customer', [
                'mode' => 'redact',
                'rules' => [['field' => 'email', 'strategy' => 'acme.module_mask']],
            ]))
            ->withReplacementStrategy(new ModuleMask());
    }
}

$anonymizer = AnonymizerBuilder::create()->withExtension(new CustomerExtension())->build();
$safe = $anonymizer->anonymize('customer', ['email' => 'secret']);

if ($safe !== ['email' => '******']) {
    throw new RuntimeException('The extension module example failed.');
}
```

## Register extensions in frameworks

Symfony can register individual ports with explicit tags and IDs, or a module:

```yaml
App\Privacy\TokenMask:
  tags:
    - { name: anonymizer.replacement_strategy, id: acme.token_mask }

App\Privacy\CustomerExtension:
  tags:
    - { name: anonymizer.extension, priority: 20 }
```

Symfony applies modules after individual ports, ordered by descending
`priority` and then service order. Existing tags include
`anonymizer.profile_provider`, `anonymizer.replacement_strategy`,
`anonymizer.payload_codec`, and `anonymizer.value_transformation`.

In Laravel, register the same module object as a singleton and tag it:

```php
$this->app->singleton(CustomerExtension::class);
$this->app->tag(CustomerExtension::class, 'anonymizer.extension');
```

Laravel applies tagged modules after individual ports in registration order.
Package discovery registers the integration provider; it does not discover
arbitrary application extension classes. See the
[Symfony Bundle](../packages/anonymizer-bundle/docs/usage.md) and
[Laravel](../packages/anonymizer-laravel/docs/usage.md) guides for service
configuration.

## IDs, failures, and contract tests

Registration is additive. Profile names, strategy IDs, codec IDs, and
transformation IDs are case-sensitive and unique in their respective
registries. IDs must match `^[A-Za-z][A-Za-z0-9_.-]*$`; duplicate IDs and
conflicts with built-ins fail when the façade is built. A builder cannot
register more than one object adapter. Profile providers must not return
duplicate profile names.

The builder materializes iterable registrations passed to its collection
methods. A profile provider is different: `profiles()` is called during each
`build()`. Keep provider output repeatable if the builder is reused.

Transformation IDs and options are validated during `build()`, including
rules that a given input may not select. Runtime value types and shape
mismatches are checked when a rule is applied. Codec failures surface as
`PayloadCodecFailure` diagnostics with operation, codec, profile, and path
context; diagnostics omit payload contents. Other extensions should fail
clearly and must not place input values, replacement values, or secrets in
exceptions, logs, or audit data.

Add contract tests for the behavior of each extension: a successful call,
invalid options, unsupported runtime types, duplicate IDs, required context,
and collaborator failures. Also verify deterministic output and that object
adapters leave their source objects unchanged.
