<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

/**
 * @phpstan-type Rule array<string, mixed>
 * @codeCoverageIgnore Migration conversion boundary.
 */
// @phpstan-ignore complexity.classLike (legacy node grammar has deliberately explicit validation branches)
final readonly class LegacyRuleNodeConverter
{
    public function __construct(private LegacyRuleValueConverter $values) {}

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    // @phpstan-ignore complexity.functionLike (legacy node conversion keeps diagnostics local to the node)
    public function convertNode(array $node): array
    {
        $name = $node['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw LegacyImportException::message('Legacy rule nodes require a non-empty name.');
        }
        if (($node['data_access'] ?? 'array') !== 'array') {
            throw LegacyImportException::message(sprintf('Rule "%s" uses unsupported object access.', $name));
        }
        if (isset($node['children'])) {
            if (!is_array($node['children']) || !array_is_list($node['children'])) {
                throw LegacyImportException::message(sprintf('Children for "%s" must be a list.', $name));
            }
            $children = [];
            foreach ($node['children'] as $child) {
                if (!is_array($child)) {
                    throw LegacyImportException::message(sprintf('Child nodes for "%s" must be maps.', $name));
                }
                /** @var array<string, mixed> $child */
                $children[] = $this->convertNode($child);
            }
            $rule = ['field' => $name, 'children' => $children];
        } else {
            $valueType = $node['value_type'] ?? $this->values->fallback($node['fallback'] ?? null);
            $rule = ['field' => $name, 'strategy' => $this->values->strategy($valueType)];
        }
        if (($node['is_array'] ?? false) === true) {
            return ['field' => $name, 'each' => $rule['children'] ?? true, 'on_scalar' => 'redact'];
        }
        return $rule;
    }

    /** @return Rule */
    public function convertPath(string $path, mixed $definition): array
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
        return $this->buildPath($fields, $eachFields, $definition, 0);
    }

    /**
     * @param list<string> $fields
     * @param list<int> $eachFields
     * @return array<string, mixed>
     */
    private function buildPath(array $fields, array $eachFields, mixed $definition, int $index): array
    {
        $field = $fields[$index];
        if ($index === count($fields) - 1) {
            $rule = $this->leaf($field, $definition);
        } else {
            $rule = ['field' => $field, 'children' => [$this->buildPath($fields, $eachFields, $definition, $index + 1)]];
        }
        if (in_array($index, $eachFields, true)) {
            return isset($rule['children']) ? ['field' => $field, 'each' => $rule['children']] : ['field' => $field, 'each' => true, 'on_scalar' => 'redact'];
        }
        return $rule;
    }

    /** @return Rule */
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
