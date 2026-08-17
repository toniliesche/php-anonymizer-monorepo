# PHP Anonymizer

This repository contains the clean v1 rewrite of PHP Anonymizer. Development
uses PHP 8.5+, Composer, and the root Composer scripts.

The first core API is intentionally small and array-only:

```php
use PhpAnonymizer\Anonymizer\Application\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Infrastructure\InMemoryProfileProvider;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'email']],
]);

$anonymizer = new ArrayAnonymizer(new InMemoryProfileProvider([$profile]));
$safe = $anonymizer->anonymize('customer', ['email' => 'user@example.com']);
// ['email' => '[REDACTED]']
```

Run `composer qa` for the complete quality gate.
