<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$manifest = require $root . '/tools/public-api-manifest.php';
$types = array_merge($manifest['supported'], $manifest['transitional']);
$violations = [];

foreach ($types as $type) {
    if (!class_exists($type) && !interface_exists($type) && !enum_exists($type)) {
        $violations[] = sprintf('Manifest type %s cannot be autoloaded.', $type);

        continue;
    }

    $reflection = new ReflectionClass($type);
    if (str_contains((string) $reflection->getDocComment(), '@internal')) {
        $violations[] = sprintf('Manifest type %s is marked @internal.', $type);
    }
}

if (!isset($manifest['signatures']) || count($manifest['signatures']) !== count($types)) {
    $violations[] = 'Manifest signatures are incomplete; run the signature dump and record every type.';
} else {
    foreach ($types as $type) {
        $actual = hash('sha256', canonicalJson(typeDescriptor(new ReflectionClass($type))));
        if (($manifest['signatures'][$type] ?? null) !== $actual) {
            $violations[] = sprintf('Public signature changed for %s.', $type);
        }
    }
}

$files = [];
foreach (glob($root . '/packages/*/src') ?: [] as $sourceDirectory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirectory));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }
    foreach (declaredTypes($contents) as $type => $docComment) {
        if (!in_array($type, $types, true) && !str_contains($docComment, '@internal')) {
            $violations[] = sprintf('%s declares %s without a public manifest entry or @internal.', substr($file, strlen($root) + 1), $type);
        }
    }
}

$apiDocument = file_get_contents($root . '/docs/api-surface-v2.md') ?: '';
foreach ($types as $type) {
    if (!str_contains($apiDocument, $type)) {
        $violations[] = sprintf('API document does not index %s.', $type);
    }
}

$exampleFiles = array_merge(
    glob($root . '/README.md') ?: [],
    glob($root . '/docs/*.md') ?: [],
    glob($root . '/packages/*/README.md') ?: [],
    glob($root . '/packages/*/docs/*.md') ?: [],
    glob($root . '/tools/*-consumer-test.php') ?: [],
);
foreach ($exampleFiles as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }
    $examples = str_ends_with($file, '.md')
        ? implode(PHP_EOL, array_map(static fn (array $match): string => $match[1], preg_match_all('/```(?:php)?\s*(.*?)```/s', $contents, $matches, PREG_SET_ORDER) === false ? [] : $matches))
        : $contents;
    foreach (projectTypes($examples) as $projectType) {
        if (!in_array($projectType, $types, true)) {
            $violations[] = sprintf('%s references non-public symbol %s.', substr($file, strlen($root) + 1), $projectType);
        }
    }
}

if (in_array('--dump-signatures', $argv, true)) {
    $signatures = [];
    foreach ($types as $type) {
        $signatures[$type] = hash('sha256', canonicalJson(typeDescriptor(new ReflectionClass($type))));
    }
    echo var_export($signatures, true), PHP_EOL;

    exit($violations === [] ? 0 : 1);
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations) . PHP_EOL);

    exit(1);
}

printf("Checked %d public API types and %d public-example files.\n", count($types), count($exampleFiles));

/**
 * @return array<string, mixed>
 */
function typeDescriptor(ReflectionClass $reflection): array
{
    $methods = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            continue;
        }
        $methods[$method->getName()] = [
            'static' => $method->isStatic(),
            'return' => reflectionType($method->getReturnType()),
            'parameters' => array_map(static fn (ReflectionParameter $parameter): array => [
                'name' => $parameter->getName(),
                'type' => reflectionType($parameter->getType()),
                'reference' => $parameter->isPassedByReference(),
                'variadic' => $parameter->isVariadic(),
                'optional' => $parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable() ? ($parameter->isDefaultValueConstant() ? $parameter->getDefaultValueConstantName() : $parameter->getDefaultValue()) : null,
            ], $method->getParameters()),
        ];
    }
    ksort($methods);

    return [
        'kind' => $reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : 'class'),
        'final' => $reflection->isFinal(),
        'abstract' => $reflection->isAbstract(),
        'parent' => ($parent = $reflection->getParentClass()) === false ? null : $parent->getName(),
        'interfaces' => array_values($reflection->getInterfaceNames()),
        'constants' => $reflection->getConstants(),
        'properties' => array_map(static fn (ReflectionProperty $property): array => [
            'name' => $property->getName(),
            'static' => $property->isStatic(),
            'readonly' => $property->isReadOnly(),
            'type' => reflectionType($property->getType()),
        ], array_filter($reflection->getProperties(ReflectionProperty::IS_PUBLIC), static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $reflection->getName())),
        'methods' => $methods,
        'enumCases' => $reflection instanceof ReflectionEnum ? array_map(static fn (object $case): string => $case->getName(), $reflection->getCases()) : [],
    ];
}

function reflectionType(?ReflectionType $type): mixed
{
    if (!$type instanceof ReflectionType) {
        return null;
    }
    if ($type instanceof ReflectionNamedType) {
        return ['kind' => 'named', 'name' => $type->getName(), 'allowsNull' => $type->allowsNull()];
    }
    if ($type instanceof ReflectionUnionType) {
        $names = array_map(reflectionType(...), $type->getTypes());
        usort($names, static fn (mixed $a, mixed $b): int => strcmp(json_encode($a), json_encode($b)));

        return ['kind' => 'union', 'types' => $names];
    }

    return (string) $type;
}

function canonicalJson(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

/**
 * @return array<string, string>
 */
function declaredTypes(string $contents): array
{
    $tokens = token_get_all($contents);
    $namespace = '';
    $doc = '';
    $result = [];
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_NAMESPACE) {
            $namespace = '';
            for (++$i; $i < $count; $i++) {
                if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $namespace .= $tokens[$i][1];
                } elseif ($tokens[$i] === ';' || $tokens[$i] === '{') {
                    break;
                }
            }

            continue;
        }
        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $doc = $token[1];

            continue;
        }
        if (is_array($token) && $token[0] === T_COMMENT && str_contains($token[1], '@internal')) {
            $doc .= $token[1];

            continue;
        }
        if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT], true)) {
            $name = null;
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $name = $tokens[$j][1];

                    break;
                }
                if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                    break;
                }
            }
            $previous = $i > 0 ? $tokens[$i - 1] : null;
            $anonymous = $token[0] === T_CLASS && is_array($previous) && $previous[0] === T_NEW;
            $classReference = is_array($previous) && $previous[0] === T_DOUBLE_COLON;
            if ($name !== null && !$anonymous && !$classReference) {
                $result[($namespace === '' ? '' : $namespace . '\\') . $name] = $doc;
            }
            $doc = '';
        }
    }

    return $result;
}

/**
 * @return list<string>
 */
function projectTypes(string $contents): array
{
    preg_match_all('/PhpAnonymizer\[A-Za-z0-9_\]+/', $contents, $matches);

    return array_values(array_unique($matches[0]));
}
