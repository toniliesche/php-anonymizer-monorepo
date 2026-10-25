<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Infrastructure\LegacyImport;

use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\glob;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\unlink;

#[CoversClass(AtomicFileWriter::class)]
#[CoversClass(BundleConfigRenderer::class)]
#[CoversClass(LegacyDocumentReader::class)]
#[UsesClass(LegacyImportException::class)]
final class LegacyImportInfrastructureTest extends TestCase
{
    public function testAtomicWriterCreatesParentDirectoryAndReplacesFile(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-import-' . bin2hex(random_bytes(5));
        $path = $directory . '/nested/config.yaml';

        try {
            new AtomicFileWriter()->write($path, "profiles:\n  customer: []\n");
            self::assertFileExists($path);
            self::assertSame("profiles:\n  customer: []\n", file_get_contents($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($directory . '/nested')) {
                rmdir($directory . '/nested');
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testAtomicWriterReportsWriteFailures(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-writer-' . bin2hex(random_bytes(5));
        mkdir($directory);

        try {
            try {
                new AtomicFileWriter()->write($directory, 'cannot replace directory');
                self::fail('Expected replacing a directory to fail.');
            } catch (LegacyImportException) {
                self::assertSame([], glob($directory . '/.anonymizer-import-*'));
            }
        } finally {
            rmdir($directory);
        }
    }

    public function testAtomicWriterReportsDirectoryCreationFailures(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-writer-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $blocker = $directory . '/file';
        file_put_contents($blocker, 'not a directory');

        try {
            $this->expectException(LegacyImportException::class);
            new AtomicFileWriter()->write($blocker . '/output.yaml', 'cannot create parent');
        } finally {
            unlink($blocker);
            rmdir($directory);
        }
    }

    public function testRendererProducesYamlWithTrailingNewline(): void
    {
        self::assertSame("profiles:\n  customer:\n    mode: redact\n\n", new BundleConfigRenderer()->render([
            'profiles' => ['customer' => ['mode' => 'redact']],
        ]));
    }

    public function testReaderSupportsJsonAndYamlAndRejectsInvalidInputs(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-reader-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $json = $directory . '/rules.json';
        $yaml = $directory . '/rules.yaml';
        file_put_contents($json, '{"profiles":{"customer":[]}}');
        file_put_contents($yaml, "profiles:\n  customer: []\n");

        try {
            $reader = new LegacyDocumentReader();
            self::assertSame(['profiles' => ['customer' => []]], $reader->read($json));
            self::assertSame(['profiles' => ['customer' => []]], $reader->read($yaml));

            $this->expectException(LegacyImportException::class);
            $this->expectExceptionMessageIsOrContains(sprintf('Input file "%s" does not exist or is not readable.', $directory . '/missing.txt'));
            $reader->read($directory . '/missing.txt');
        } finally {
            unlink($json);
            unlink($yaml);
            rmdir($directory);
        }
    }

    public function testReaderRejectsUnsupportedExtensionAndNonMapDocuments(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-reader-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $text = $directory . '/rules.txt';
        file_put_contents($text, 'profiles');

        try {
            $reader = new LegacyDocumentReader();
            $this->expectException(LegacyImportException::class);
            $this->expectExceptionMessageIsOrContains('Input must have a .yaml, .yml, or .json extension.');
            $reader->read($text);
        } finally {
            unlink($text);
            rmdir($directory);
        }
    }

    public function testReaderRejectsScalarDocuments(): void
    {
        $path = sys_get_temp_dir() . '/anonymizer-scalar-' . bin2hex(random_bytes(5)) . '.json';
        file_put_contents($path, '"scalar"');

        try {
            $this->expectException(LegacyImportException::class);
            new LegacyDocumentReader()->read($path);
        } finally {
            unlink($path);
        }
    }

    public function testReaderReportsMalformedJsonAndIntegerMappingKeys(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-reader-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $malformed = $directory . '/malformed.json';
        $integerKey = $directory . '/integer.yaml';
        file_put_contents($malformed, '{invalid');
        file_put_contents($integerKey, "1: value\n");

        try {
            $reader = new LegacyDocumentReader();

            try {
                $reader->read($malformed);
                self::fail('Malformed JSON should be rejected.');
            } catch (LegacyImportException $exception) {
                self::assertStringContainsString('Unable to parse input', $exception->getMessage());
                self::assertStringContainsString('Syntax error', $exception->getMessage());
            }
            $this->expectException(LegacyImportException::class);
            $reader->read($integerKey);
        } finally {
            unlink($malformed);
            unlink($integerKey);
            rmdir($directory);
        }
    }

    public function testReaderHonorsCaseInsensitiveExtensionsAndJsonDepthBoundary(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-reader-depth-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $valid = $directory . '/valid.json';
        $uppercase = $directory . '/UPPER.JSON';
        $tooDeep = $directory . '/too-deep.json';
        $validJson = str_repeat('{"a":', 511) . '0' . str_repeat('}', 511);
        $tooDeepJson = str_repeat('{"a":', 512) . '0' . str_repeat('}', 512);
        file_put_contents($valid, $validJson);
        file_put_contents($uppercase, $validJson);
        file_put_contents($tooDeep, $tooDeepJson);

        try {
            $reader = new LegacyDocumentReader();
            $reader->read($valid);
            $reader->read($uppercase);

            try {
                $reader->read($tooDeep);
                self::fail('JSON at the configured depth boundary should be rejected.');
            } catch (LegacyImportException $exception) {
                self::assertStringContainsString('Unable to parse input', $exception->getMessage());
            }
        } finally {
            unlink($valid);
            unlink($uppercase);
            unlink($tooDeep);
            rmdir($directory);
        }
    }

    public function testReaderSupportsYmlAndRequiresAnAssociativeRoot(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-reader-yml-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $yml = $directory . '/rules.YML';
        $rootList = $directory . '/list.json';
        file_put_contents($yml, "profiles:\n  customer:\n    mode: redact\nversion: 1\n");
        file_put_contents($rootList, '[1,2]');

        try {
            $reader = new LegacyDocumentReader();
            self::assertSame(['profiles' => ['customer' => ['mode' => 'redact']], 'version' => 1], $reader->read($yml));

            try {
                $reader->read($rootList);
                self::fail('A root list is not a mapping document.');
            } catch (LegacyImportException $exception) {
                self::assertSame('The input document must use string mapping keys.', $exception->getMessage());
            }
        } finally {
            unlink($yml);
            unlink($rootList);
            rmdir($directory);
        }
    }
}
