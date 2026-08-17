<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/packages', __DIR__ . '/tests', __DIR__ . '/tools'])
    ->exclude(['var', 'vendor']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'strict_param' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_order' => true,
        'phpdoc_trim' => true,
        'phpdoc_var_without_name' => true,
    ])
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer.php')
    ->setFinder($finder);
