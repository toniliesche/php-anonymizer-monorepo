<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport;

use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Webmozart\Assert\Assert;
use function mb_strtolower;
use function Safe\file_get_contents;
use function Safe\json_decode;

/**
 * @codeCoverageIgnore Input adapter is covered by command integration tests.
 */
// @internal
final class LegacyDocumentReader
{
    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw LegacyImportException::message(sprintf('Input file "%s" does not exist or is not readable.', $path));
        }
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION), 'UTF-8');

        try {
            $document = match ($extension) {
                'yaml', 'yml' => Yaml::parseFile($path),
                'json' => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
                default => throw LegacyImportException::message('Input must have a .yaml, .yml, or .json extension.'),
            };
        } catch (LegacyImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw LegacyImportException::message('Unable to parse input: ' . $exception->getMessage());
        }
        if (!is_array($document)) {
            throw LegacyImportException::message('The input document must contain a map at its root.');
        }
        foreach (array_keys($document) as $key) {
            if (!is_string($key)) {
                throw LegacyImportException::message('The input document must use string mapping keys.');
            }
        }

        Assert::isMap($document);

        return $document;
    }
}
