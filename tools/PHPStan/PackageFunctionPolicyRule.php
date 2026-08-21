<?php

declare(strict_types=1);

namespace PhpAnonymizer\Workspace\PHPStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class PackageFunctionPolicyRule implements Rule
{
    private const array DISALLOWED_LANGUAGE_FUNCTIONS = ['eval', 'assert'];

    /** @var array<string, int> */
    private const array MB_ENCODING_ARGUMENT = [
        'mb_strlen' => 1, 'mb_substr' => 3, 'mb_substr_count' => 2, 'mb_strpos' => 3,
        'mb_stripos' => 3, 'mb_strrpos' => 3, 'mb_strripos' => 3, 'mb_strstr' => 3,
        'mb_stristr' => 3, 'mb_strtolower' => 1, 'mb_strtoupper' => 1, 'mb_str_split' => 2,
    ];

    #[Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!str_starts_with($scope->getFile(), dirname(__DIR__, 2) . '/packages/')) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        $name = mb_strtolower($node->name->toString(), 'UTF-8');
        if (in_array($name, self::DISALLOWED_LANGUAGE_FUNCTIONS, true)) {
            return [RuleErrorBuilder::message(sprintf('Function %s() is disallowed in published packages.', $name))->identifier('anonymizer.disallowedFunction')->build()];
        }

        if (isset(self::MB_ENCODING_ARGUMENT[$name])) {
            $position = self::MB_ENCODING_ARGUMENT[$name];
            $argument = $node->args[$position] ?? null;
            if ($argument === null || !$argument instanceof Arg || !$argument->value instanceof String_ || mb_strtoupper($argument->value->value, 'UTF-8') !== 'UTF-8') {
                return [RuleErrorBuilder::message(sprintf("Function %s() must pass the explicit encoding 'UTF-8'.", $name))->identifier('anonymizer.explicitEncoding')->build()];
            }
        }

        return [];
    }
}
