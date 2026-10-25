# Extension author guide

PHP Anonymizer extensions are framework-independent PHP objects. Applications
register them through the immutable `AnonymizerBuilder`; Symfony and Laravel
can discover the same objects through their documented tags.

## The builder and modules

Registration is eager and additive: IDs are case-sensitive, duplicate IDs and
built-in IDs fail during `build()`, and a module cannot override an earlier
registration.

```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

final readonly class HashReplacement implements ReplacementStrategyInterface
{
    public function id(): string { return 'acme.hash'; }

    public function replace(string $value): string
    {
        return hash('sha256', $value);
    }
}

$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('customer', [
        'mode' => 'redact',
        'rules' => [['field' => 'email', 'strategy' => 'acme.hash']],
    ]))
    ->withReplacementStrategy(new HashReplacement())
    ->build();
```

Use a module when several related ports belong together:

```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Extension\AnonymizerExtensionInterface;

final readonly class AcmeExtension implements AnonymizerExtensionInterface
{
    public function configure(AnonymizerBuilder $builder): AnonymizerBuilder
    {
        return $builder
            ->withProfileProvider(new AcmeProfileProvider())
            ->withReplacementStrategy(new HashReplacement());
    }
}

$anonymizer = AnonymizerBuilder::create()
    ->withExtension(new AcmeExtension())
    ->build();
```

Builders are immutable and reusable. Iterables are materialized when
registered, so a generator can safely be supplied once without changing a
later `build()` result.

## Extension ports

### Profiles

`ProfileProviderInterface::profiles()` returns immutable
`ProfileDefinition` values. Provider names must be unique across configured
profiles and providers. Providers are not scanned implicitly and must not
expose source data, replacement values, or secrets.

### Replacement strategies

Implement `ReplacementStrategyInterface` for a string-to-string handler.
Contextual handlers implement `ContextualReplacementStrategyInterface` and
receive profile, JSON Pointer, key ID, and application attributes through
`ReplacementContext`. IDs must match `^[A-Za-z][A-Za-z0-9_.-]*$`.

### Payload and document codecs

`PayloadCodecInterface` decodes and encodes associative maps. A codec that
supports document roots may additionally implement
`DocumentPayloadCodecInterface`; document methods may return either a map or a
list. Codec failures should preserve the operation, codec ID, profile, and
path in the public `PayloadCodecFailure` diagnostic without including payload
contents.

### Value transformations

`ValueTransformationInterface` declares supported input types, output type,
options validation, and transformation. Contextual transformations implement
`ContextualValueTransformationInterface` and receive a
`ValueTransformationContext`. Validate every named option and reject wrong
runtime values with a stable `InvalidArgumentException` or the documented
profile exception.

### Object adapters

`ObjectAnonymizerInterface` receives an immutable
`ObjectAnonymizationRequest` and the generic façade. Adapters must not mutate
the source object and must return the source runtime class. Adapter-specific
options belong in `adapterOptions()` and are opaque to the core. A façade
without an adapter raises `ObjectAnonymizationUnavailable`.

## Framework registration

Symfony applications can tag a module, or register individual ports:

```yaml
App\Privacy\AcmeExtension:
  tags:
    - { name: anonymizer.extension, priority: 20 }
```

Symfony modules are applied in descending `priority`, then service order.
Existing `anonymizer.profile_provider`,
`anonymizer.replacement_strategy`, `anonymizer.payload_codec`, and
`anonymizer.value_transformation` tags remain supported.

Laravel packages register the same module through the service container:

```php
$this->app->singleton(AcmeExtension::class);
$this->app->tag(AcmeExtension::class, 'anonymizer.extension');
```

Laravel applies tagged modules in registration order. Package discovery only
registers the provider; it does not scan arbitrary application classes.

## Diagnostics, compatibility, and tests

Never put source values, replacement values, secrets, or raw payloads in an
exception, audit entry, or profile inspection result. Stable IDs and the
documented extension interfaces are covered by the 2.0 semver contract.
Concrete registries, built-ins, parsers, traversal classes, framework
registrars, and legacy-import collaborators are `@internal` and may change
without a semver guarantee.

Every extension should have a contract suite covering success, invalid options,
wrong runtime types, duplicate IDs, missing context, and thrown collaborator
failures. A minimal strategy contract is:

```php
#[CoversClass(HashReplacement::class)]
final class HashReplacementTest extends TestCase
{
    public function testItHasAStableIdAndDoesNotReturnTheSourceValue(): void
    {
        $strategy = new HashReplacement();

        self::assertSame('acme.hash', $strategy->id());
        self::assertNotSame('secret', $strategy->replace('secret'));
    }
}
```

For profile, codec, transformation, object, and module ports, add assertions
for the corresponding public failure contract and verify that all supplied
iterables and contexts are handled deterministically.
