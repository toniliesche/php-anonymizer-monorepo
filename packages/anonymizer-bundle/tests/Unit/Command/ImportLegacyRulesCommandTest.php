<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Command;

use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileCompositionCompiler;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionData;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileGlobalRuleParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadataParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRuleAppender;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\EmbeddedPayloadDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleTraversalCombinationValidator;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\unlink;

#[CoversClass(ImportLegacyRulesCommand::class)]
#[UsesClass(LegacyRuleCollectionConverter::class)]
#[UsesClass(LegacyImportException::class)]
#[UsesClass(LegacyRuleConverter::class)]
#[UsesClass(LegacyRuleMerger::class)]
#[UsesClass(LegacyRuleNodeConverter::class)]
#[UsesClass(LegacyRulePathConverter::class)]
#[UsesClass(LegacyRuleValueConverter::class)]
#[UsesClass(AtomicFileWriter::class)]
#[UsesClass(BundleConfigRenderer::class)]
#[UsesClass(LegacyDocumentReader::class)]
#[UsesClass(ProfileCompositionCompiler::class)]
#[UsesClass(ProfileDefinition::class)]
#[UsesClass(ProfileDefinitionData::class)]
#[UsesClass(ProfileDefinitionParser::class)]
#[UsesClass(ProfileMetadataParser::class)]
#[UsesClass(ProfileRuleAppender::class)]
#[UsesClass(RuleOptionsCompiler::class)]
#[UsesClass(RuleDefinition::class)]
#[UsesClass(ProfileGlobalRuleParser::class)]
#[UsesClass(EmbeddedPayloadDefinitionParser::class)]
#[UsesClass(RuleTraversalCombinationValidator::class)]
final class ImportLegacyRulesCommandTest extends TestCase
{
    public function testConvertsAndWritesConfigurationAndSupportsCheckMode(): void
    {
        $directory = sys_get_temp_dir() . '/anonymizer-command-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $input = $directory . '/legacy.json';
        $output = $directory . '/config.yaml';
        file_put_contents($input, '{"rules":{"email":"email"}}');
        $command = new ImportLegacyRulesCommand($this->reader(), $this->converter(), new BundleConfigRenderer(), new AtomicFileWriter());

        try {
            $tester = new CommandTester($command);
            self::assertSame(0, $tester->execute(['input' => $input, 'output' => $output]));
            self::assertSame(sprintf("Wrote %s.\n", $output), $tester->getDisplay());
            self::assertSame(0, $tester->execute(['input' => $input, 'output' => $output, '--check' => true]));
            self::assertSame("Output is current.\n", $tester->getDisplay());
            file_put_contents($output, 'outdated');
            self::assertSame(1, $tester->execute(['input' => $input, 'output' => $output, '--check' => true]));
            self::assertSame("Output is outdated.\n", $tester->getDisplay());
            unlink($output);
            self::assertSame(1, $tester->execute(['input' => $input, 'output' => $output, '--check' => true]));
            self::assertSame("Output is missing.\n", $tester->getDisplay());
        } finally {
            unlink($input);
            if (is_file($output)) {
                unlink($output);
            }
            rmdir($directory);
        }
    }

    public function testInvalidInputReturnsInvalidStatus(): void
    {
        $command = new ImportLegacyRulesCommand($this->reader(), $this->converter(), new BundleConfigRenderer(), new AtomicFileWriter());
        $tester = new CommandTester($command);

        self::assertSame(2, $tester->execute(['input' => '/missing.json', 'output' => '/tmp/config.yaml']));
        self::assertSame("Input file \"/missing.json\" does not exist or is not readable.\n", $tester->getDisplay());
    }

    public function testCommandFormatsErrorDiagnosticsWhenOutputIsDecorated(): void
    {
        $command = new ImportLegacyRulesCommand($this->reader(), $this->converter(), new BundleConfigRenderer(), new AtomicFileWriter());
        $tester = new CommandTester($command);

        self::assertSame(2, $tester->execute(
            ['input' => '/missing.json', 'output' => '/tmp/config.yaml'],
            ['decorated' => true],
        ));
        self::assertSame("\033[37;41mInput file \"/missing.json\" does not exist or is not readable.\033[39;49m\n", $tester->getDisplay());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInvalidInputTypesReturnInvalidStatus(): void
    {
        $execute = new ReflectionMethod(ImportLegacyRulesCommand::class, 'execute');
        foreach ([
            [42, '/tmp/config.yaml', false],
            ['/missing.json', null, false],
            ['/missing.json', '/tmp/config.yaml', null],
        ] as [$inputPath, $outputPath, $check]) {
            $input = $this->createMock(InputInterface::class);
            $input->method('getArgument')->willReturnOnConsecutiveCalls($inputPath, $outputPath);
            $input->method('getOption')->willReturn($check);
            $output = new BufferedOutput();

            self::assertSame(2, $execute->invoke(new ImportLegacyRulesCommand($this->reader(), $this->converter(), new BundleConfigRenderer(), new AtomicFileWriter()), $input, $output));
            self::assertSame('', $output->fetch());
        }
    }

    private function reader(): LegacyDocumentReader
    {
        return new LegacyDocumentReader();
    }

    private function converter(): LegacyRuleConverter
    {
        $value = new LegacyRuleValueConverter();

        return new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter($value, new LegacyRulePathConverter($value))), new LegacyRuleMerger());
    }
}
