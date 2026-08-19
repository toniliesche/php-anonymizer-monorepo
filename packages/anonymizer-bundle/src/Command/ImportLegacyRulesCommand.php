<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Command;

use Override;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;

use function Safe\file_get_contents;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/** @codeCoverageIgnore CLI adapter is covered by command integration tests. */
#[AsCommand(name: 'anonymizer:import-legacy-rules', description: 'Convert legacy PHP Anonymizer rules into Bundle configuration.')]
final class ImportLegacyRulesCommand extends Command
{
    public function __construct(private readonly LegacyDocumentReader $reader, private readonly LegacyRuleConverter $converter, private readonly BundleConfigRenderer $renderer, private readonly AtomicFileWriter $writer)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Legacy YAML or JSON file')
            ->addArgument('output', InputArgument::REQUIRED, 'Complete Bundle YAML output file')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Check whether output is current without writing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $inputPath = $input->getArgument('input');
            $outputPath = $input->getArgument('output');
            $check = $input->getOption('check');
            if (!is_string($inputPath) || !is_string($outputPath) || !is_bool($check)) {
                return Command::INVALID;
            }
            /** @var array<string, mixed> $configuration */
            $configuration = $this->converter->convert($this->reader->read($inputPath));
            $rendered = $this->renderer->render($configuration);
            if ($check) {
                if (!is_file($outputPath)) {
                    $output->writeln('<comment>Output is missing.</comment>');
                    return Command::FAILURE;
                }
                if (file_get_contents($outputPath) !== $rendered) {
                    $output->writeln('<comment>Output is outdated.</comment>');
                    return Command::FAILURE;
                }
                $output->writeln('<info>Output is current.</info>');
                return Command::SUCCESS;
            }
            $this->writer->write($outputPath, $rendered);
            $output->writeln(sprintf('<info>Wrote %s.</info>', $outputPath));
            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::INVALID;
        }
    }
}
