<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

// @internal
final readonly class LegacyRulePathConverter
{
    public function __construct(private LegacyRuleValueConverter $values)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(string $path, mixed $definition): array
    {
        $parts = explode('.', trim($path, '.'));
        if ($parts === ['']) {
            throw LegacyImportException::message('A legacy rule path cannot be empty.');
        }

        $fields = [];
        $eachFields = [];
        foreach ($parts as $part) {
            if ($part === '') {
                throw LegacyImportException::message(sprintf('Invalid legacy path "%s".', $path));
            }
            if (str_ends_with($part, '[]')) {
                $eachFields[] = count($fields);
                $part = mb_substr($part, 0, -2, 'UTF-8');
            }
            if ($part === '') {
                throw LegacyImportException::message(sprintf('Invalid legacy path "%s".', $path));
            }
            $fields[] = $part;
        }

        return $this->build($fields, $eachFields, $definition);
    }

    /**
     * @param list<string> $fields
     * @param list<int> $eachFields
     *
     * @return array<string, mixed>
     */
    private function build(array $fields, array $eachFields, mixed $definition, int $index = 0): array
    {
        $field = $fields[$index];
        $rule = $index === count($fields) - 1
            ? $this->leaf($field, $definition)
            : ['field' => $field, 'children' => [$this->build($fields, $eachFields, $definition, $index + 1)]];

        return in_array($index, $eachFields, true)
            ? (isset($rule['children']) ? ['field' => $field, 'each' => $rule['children']] : ['field' => $field, 'each' => true, 'on_scalar' => 'redact'])
            : $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function leaf(string $field, mixed $definition): array
    {
        if (is_array($definition) && array_key_exists('name', $definition)) {
            if (($definition['data_access'] ?? 'array') !== 'array') {
                throw LegacyImportException::message(sprintf('Rule "%s" uses unsupported object access.', $field));
            }
            $definition = $definition['value_type'] ?? $this->values->fallback($definition['fallback'] ?? null);
        }
        if (is_string($definition) && str_starts_with($definition, '[#') && str_ends_with($definition, ']')) {
            $definition = mb_substr($definition, 2, -1, 'UTF-8');
        }

        return ['field' => $field, 'strategy' => $this->values->strategy($definition)];
    }
}
