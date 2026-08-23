<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

use Webmozart\Assert\Assert;

/**
 * @phpstan-type Rule array<string, mixed>
 *
 * @codeCoverageIgnore Legacy conversion diagnostics are exercised through command-level integration tests.
 *
 * @internal
 */
final readonly class LegacyRuleNodeConverter
{
    public function __construct(private LegacyRuleValueConverter $values, private LegacyRulePathConverter $paths)
    {
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    public function convertNode(array $node): array
    {
        $name = $node['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw LegacyImportException::message('Legacy rule nodes require a non-empty name.');
        }
        // @codeCoverageIgnoreStart Exception-only diagnostic branch.
        if (($node['data_access'] ?? 'array') !== 'array') {
            throw LegacyImportException::message(sprintf('Rule "%s" uses unsupported object access.', $name));
        }
        // @codeCoverageIgnoreEnd
        if (isset($node['children'])) {
            if (!is_array($node['children']) || !array_is_list($node['children'])) {
                throw LegacyImportException::message(sprintf('Children for "%s" must be a list.', $name));
            }
            $rule = ['field' => $name, 'children' => $this->convertChildren($name, $node['children'])];
        } else {
            $valueType = $node['value_type'] ?? $this->values->fallback($node['fallback'] ?? null);
            $rule = ['field' => $name, 'strategy' => $this->values->strategy($valueType)];
        }
        if (($node['is_array'] ?? false) === true) {
            return ['field' => $name, 'each' => $rule['children'] ?? true, 'on_scalar' => 'redact'];
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    public function convertPath(string $path, mixed $definition): array
    {
        return $this->paths->convert($path, $definition);
    }

    /**
     * @param list<mixed> $children
     *
     * @return list<array<string, mixed>>
     */
    private function convertChildren(string $name, array $children): array
    {
        $converted = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                throw LegacyImportException::message(sprintf('Child nodes for "%s" must be maps.', $name));
            }

            Assert::isMap($child);
            $converted[] = $this->convertNode($child);
        }

        return $converted;
    }
}
