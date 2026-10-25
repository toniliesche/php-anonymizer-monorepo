<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

use Webmozart\Assert\Assert;

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
                $left = $merged[$index]['children'];
                Assert::isList($left);
                Assert::allIsMap($left);

                $right = $rule['children'];
                Assert::isList($right);
                Assert::allIsMap($right);

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
