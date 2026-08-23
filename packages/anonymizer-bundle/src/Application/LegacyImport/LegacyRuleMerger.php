<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

/**
 * @phpstan-type Rule array<string, mixed>
 *
 * @internal
 */
final class LegacyRuleMerger
{
    /**
     * @param list<Rule> $rules
     *
     * @return list<array<string, mixed>>
     */
    public function merge(array $rules): array
    {
        $merged = [];
        foreach ($rules as $rule) {
            $index = $this->indexOf($merged, $rule['field']);
            if ($index === null) {
                $merged[] = $rule;

                continue;
            }
            if (isset($merged[$index]['children'], $rule['children'])) {
                /** @var list<Rule> $left */ $left = $merged[$index]['children'];

                /** @var list<Rule> $right */ $right = $rule['children'];
                $merged[$index]['children'] = $this->merge([...$left, ...$right]);

                continue;
            }
            if ($merged[$index] !== $rule) {
                $field = $rule['field'];

                throw LegacyImportException::message(sprintf('Conflicting legacy rules for field "%s".', is_string($field) ? $field : 'unknown'));
            }
        }

        return $merged;
    }

    /**
     * @param list<Rule> $rules
     */
    private function indexOf(array $rules, mixed $field): ?int
    {
        foreach ($rules as $index => $rule) {
            if ($rule['field'] === $field) {
                return $index;
            }
        }

        return null;
    }
}
