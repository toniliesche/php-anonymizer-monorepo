# Extension author guide

The core accepts three extension ports. They are plain PHP interfaces and do
not depend on Symfony or any container.

## Profile providers

`ProfileProviderInterface::profiles()` returns an iterable of immutable
`ProfileDefinition` objects. The anonymizer eagerly rejects duplicate profile
names and validates every strategy, codec, and embedded-profile reference.
Embedded profile references must be acyclic.

## Replacement strategies

Implement `ReplacementStrategyInterface`:

```php
final readonly class StarMask implements ReplacementStrategyInterface
{
    public function id(): string
    {
        return 'star_mask';
    }

    public function replace(string $value): string
    {
        return str_repeat('*', mb_strlen($value, 'UTF-8'));
    }
}
```

Strategies are string-to-string and are selected by a leaf rule's `strategy`
ID. IDs are case-sensitive and must match
`^[A-Za-z][A-Za-z0-9_.-]*$`. Duplicate IDs, including duplicates of built-ins,
are configuration errors.

## Payload codecs

Implement `PayloadCodecInterface`:

```php
final readonly class PipeCodec implements PayloadCodecInterface
{
    public function id(): string { return 'pipe'; }

    public function decode(string $payload): array
    {
        return ['email' => $payload];
    }

    public function encode(array $data): string
    {
        return (string) $data['email'];
    }
}
```

Codecs decode and encode associative maps. They can be selected by the
top-level payload API or by an `embedded: {codec, profile}` rule. Codec errors
should be reported with the profile, operation, codec ID, and embedded JSON
Pointer path supplied by the core.

Register extensions through the factory; applications do not need to construct
the concrete engine or profile provider:

```php
$anonymizer = AnonymizerFactory::fromProfiles(
    [$profile],
    strategies: [new StarMask()],
    codecs: [new PipeCodec()],
);
```
Typed value transformations implement `ValueTransformationInterface`, declare
supported input types and output type, and validate their named-map options.
Register custom implementations through the `anonymizer.value_transformation`
Bundle tag with a unique `id`. Contextual implementations may receive the
profile, JSON Pointer, key ID, and application attributes through
`ContextualValueTransformationInterface`.
