# Keyed pseudonymization

Install `php-anonymizer/anonymizer-pseudonymization` with the core and provide
an application-owned `PseudonymKeyProviderInterface`. Register
`HmacPseudonymStrategy`, use the `pseudonymize` strategy with a non-secret
`key_id`, and call a contextual façade method with scope or tenant attributes.

The strategy uses keyed, domain-separated HMAC output. Key material is never
read from profiles or emitted in reports, exceptions, audit entries, or logs.

See the repository [`user guide`](../../../docs/user-guide.md) and
[`extension author guide`](../../../docs/extension-author-guide.md).

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;

$keys = new class implements PseudonymKeyProviderInterface {
    public function keyFor(string $keyId): string { return 'application-secret-' . $keyId; }
};
$anonymizer = AnonymizerBuilder::create()
    ->withProfile(ProfileDefinition::fromArray('customer', ['mode' => 'redact', 'rules' => [['field' => 'email', 'strategy' => 'pseudonymize', 'key_id' => 'primary']]]))
    ->withReplacementStrategy(new HmacPseudonymStrategy($keys))
    ->build();
$context = new AnonymizationContext(['tenant' => 'acme']);
$first = $anonymizer->anonymize('customer', ['email' => 'ada@example.test'], $context);
$second = $anonymizer->anonymize('customer', ['email' => 'ada@example.test'], $context);

if ($first !== $second || $first['email'] === 'ada@example.test') {
    throw new RuntimeException('The contextual pseudonym example failed.');
}
```
