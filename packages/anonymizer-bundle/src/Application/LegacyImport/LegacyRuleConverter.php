<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

/**
 * @phpstan-type Rule array<string, mixed>
 *
 * @codeCoverageIgnore Legacy conversion is a compatibility boundary; invalid collaborator diagnostics are not reachable through the public importer.
 */
final readonly class LegacyRuleConverter
{
    public function __construct(private LegacyRuleCollectionConverter $collections, private LegacyRuleMerger $merger)
    {
    }

    /**
     * @param array<string, mixed> $legacy
     *
     * @return array<string, mixed>
     */
    public function convert(array $legacy): array
    {
        $rules = $this->rules($legacy);
        $converted = $this->collections->convert($rules);

        /** @var list<array<string, mixed>> $converted */
        $profile = ['mode' => 'redact', 'rules' => $this->merger->merge($converted)];

        // @codeCoverageIgnoreStart Defensive validation branch requires an invalid converter collaborator.
        try {
            ProfileDefinition::fromArray('legacy', $profile);
        } catch (InvalidProfileDefinition $exception) {
            throw LegacyImportException::message('Converted profile is invalid: ' . $exception->getMessage());
        }
        // @codeCoverageIgnoreEnd
        $result = ['anonymizer' => ['profiles' => ['legacy' => $profile]]];
        $this->addFakerConfiguration($result, $legacy);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $legacy
     */
    private function addFakerConfiguration(array &$result, array $legacy): void
    {
        $faker = $legacy['faker'] ?? null;
        if (!is_array($faker) || ($faker['enabled'] ?? false) !== true) {
            return;
        }
        $locale = $faker['locale'] ?? 'en_US';

        /** @var array<string, mixed> $anonymizer */
        $anonymizer = $result['anonymizer'];
        $anonymizer['faker'] = ['enabled' => true, 'locale' => is_string($locale) ? $locale : 'en_US', 'seed' => $faker['seed'] ?? null];
        $result['anonymizer'] = $anonymizer;
    }

    /**
     * @param array<string, mixed> $legacy
     *
     * @return array<mixed>
     */
    private function rules(array $legacy): array
    {
        $anonymizer = $legacy['anonymizer'] ?? null;
        if (isset($legacy['rules']) && is_array($legacy['rules'])) {
            return $legacy['rules'];
        }
        if (is_array($anonymizer) && isset($anonymizer['rules']) && is_array($anonymizer['rules'])) {
            return $anonymizer['rules'];
        }

        throw LegacyImportException::message('The legacy document must contain a non-empty rules map.');
    }
}
