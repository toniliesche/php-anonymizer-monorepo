<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$maps = [];

foreach ([$root . '/composer.json', ...glob($root . '/packages/*/composer.json')] as $manifest) {
    $package = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $packageRoot = dirname($manifest);
    foreach (['autoload', 'autoload-dev'] as $section) {
        foreach ($package[$section]['psr-4'] ?? [] as $prefix => $directory) {
            $maps[$prefix] = $packageRoot . '/' . $directory;
        }
    }
}

$parser = new ParserFactory()->createForNewestSupportedVersion();
$violations = [];
$allowlistedClosureFiles = [
    '/packages/anonymizer-laravel/src/AnonymizerServiceProvider.php',
    '/packages/anonymizer-laravel/src/Infrastructure/Audit/AuditRegistrar.php',
    '/packages/anonymizer-bundle/tests/Integration/TestKernel.php',
];

foreach ($maps as $prefix => $directory) {
    if (!is_dir($directory)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $relative = substr($path, strlen($directory) + 1);
        $nodes = $parser->parse((string) file_get_contents($path)) ?? [];
        $visitor = new class ($path, $prefix, $relative, $allowlistedClosureFiles, $root, $violations) extends NodeVisitorAbstract {
            /**
             * @param list<string> $allowlistedClosureFiles
             */
            public function __construct(
                private readonly string $path,
                private readonly string $prefix,
                private readonly string $relative,
                private readonly array $allowlistedClosureFiles,
                private readonly string $root,
                /** @var list<string> */
                public array $violations,
            ) {
            }

            public function enterNode(Node $node): void
            {
                if ($node instanceof Class_ && !$node->name instanceof Identifier) {
                    $this->violations[] = $this->path . ':' . $node->getStartLine() . ': anonymous classes are not allowed';
                }
                if ($node instanceof ClassLike && $node->name instanceof Identifier) {
                    $namespace = $node->getAttribute('namespacedName')?->toString() ?? '';
                    $expected = null;
                    if ($namespace !== '') {
                        $fqcn = $namespace . '\\' . $node->name->toString();
                        if (str_starts_with($fqcn, $this->prefix)) {
                            $expected = str_replace('\\', '/', substr($fqcn, strlen($this->prefix))) . '.php';
                        }
                    }
                    if ($expected !== null && $expected !== $this->relative) {
                        $this->violations[] = $this->path . ':' . $node->getStartLine() . ': ' . $namespace . '\\' . $node->name->toString() . ' must be in ' . $expected;
                    }
                }
                if ($node instanceof Closure && !in_array(str_replace($this->root, '', $this->path), $this->allowlistedClosureFiles, true)) {
                    $this->violations[] = $this->path . ':' . $node->getStartLine() . ': deferred closures are not allowed outside framework seams';
                }
            }
        };
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
        $violations = $visitor->violations;
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($violations)) . PHP_EOL);

    exit(1);
}

echo 'Structural PSR-4 and anonymous-code checks passed.' . PHP_EOL;
