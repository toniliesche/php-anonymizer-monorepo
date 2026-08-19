<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport;

use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;

use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\rename;
use function Safe\tempnam;
use function Safe\unlink;

use Throwable;

/** @codeCoverageIgnore Filesystem failure paths are covered by integration tests. */
final class AtomicFileWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            try {
                mkdir($directory, 0777, true);
            } catch (Throwable $exception) {
                throw LegacyImportException::message(sprintf('Unable to create output directory "%s": %s', $directory, $exception->getMessage()));
            }
        }
        $temporary = tempnam($directory, '.anonymizer-import-');
        try {
            try {
                file_put_contents($temporary, $contents);
                rename($temporary, $path);
            } catch (Throwable $exception) {
                throw LegacyImportException::message(sprintf('Unable to write output file "%s": %s', $path, $exception->getMessage()));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
