<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

final readonly class LegacyRuleCollectionConverter
{
    public function __construct(private LegacyRuleNodeConverter $nodes)
    {
    }

    /**
     * @param array<mixed> $rules
     *
     * @return list<array<string, mixed>>
     */
    public function convert(array $rules): array
    {
        return array_is_list($rules) ? $this->convertNodes($rules) : $this->convertPaths($rules);
    }

    /** @param list<mixed> $rules
     * @return list<array<string, mixed>> */
    private function convertNodes(array $rules): array
    {
        $converted = [];
        foreach ($rules as $definition) {
            if (!is_array($definition)) {
                throw LegacyImportException::message('Legacy rule nodes must be maps.');
            }
            foreach (array_keys($definition) as $nodeKey) {
                if (!is_string($nodeKey)) {
                    throw LegacyImportException::message('Legacy rule nodes must use string keys.');
                }
            }

            /** @var array<string, mixed> $definition */
            $converted[] = $this->nodes->convertNode($definition);
        }

        return $converted;
    }

    /** @param array<mixed, mixed> $rules
     * @return list<array<string, mixed>> */
    private function convertPaths(array $rules): array
    {
        $converted = [];
        foreach ($rules as $key => $definition) {
            if (!is_string($key)) {
                throw LegacyImportException::message('Legacy rule paths must be strings.');
            }
            $converted[] = $this->nodes->convertPath($key, $definition);
        }

        return $converted;
    }
}
