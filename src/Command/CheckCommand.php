<?php

namespace LatteSyntaxChecker\Command;

use Latte\CompileException;
use Latte\Engine;
use Nette\Application\UI\ITemplateFactory;
use Nette\DI\Container;
use Nette\Utils\Finder;
use Overtrue\PHPLint\Linter;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('check')
            ->setDescription('Command compiles all *.latte files and then checks syntax of all generated *.php files')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of directories to check')
            ->addOption('bootstrap', 'b', InputOption::VALUE_REQUIRED, 'Bootstrap file which returns container')
            ->addOption('compiled-dir', 'c', InputOption::VALUE_REQUIRED, 'Directory for compiled files', 'tmp/compiled')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirs = $input->getArgument('dirs');
        $latte = $this->getLatteEngine($input);

        $sourceDirs = [];
        foreach ($dirs as $dir) {
            $source = realpath($dir);
            if ($source === false) {
                continue;
            }
            $sourceDirs[] = $source;
        }

        $compiledDir = $input->getOption('compiled-dir');
        if (!file_exists($compiledDir)) {
            mkdir($compiledDir, 0777, true);
        }

        $errors = [];
        /* @var SplFileInfo $file */
        foreach (Finder::findFiles('*.latte')->from($sourceDirs) as $file) {
            $fileName = $file->getRealPath();

            $tempFile = $compiledDir . str_replace(['.latte'], ['.php'], $fileName);

            $tempDirName = pathinfo($tempFile, PATHINFO_DIRNAME);
            if (!file_exists($tempDirName)) {
                mkdir($tempDirName, 0777, true);
            }
            try {
                $code = $latte->compile($fileName);
                file_put_contents($tempFile, $code);
                $sources[realpath($tempFile)] = $fileName;
            } catch (CompileException $e) {
                $errors[] = [
                    'file' => $e->sourceName,
                    'error' => $e->getMessage(),
                    'source' => $e->sourceName,
                    'content' => file_get_contents($e->sourceName),
                    'line' => $e->sourceLine,
                ];
            }
        }

        $linter = new Linter($compiledDir);
        foreach ($linter->lint($linter->getFiles()) as $key => $error) {
            $errors[] = [
                'file' => $error['file'],
                'error' => $error['error'],
                'source' => $sources[$key] ?? null,
                'content' => file_get_contents($error['file']),
                'line' => $error['line'],
            ];
        }

        $errorsCount = count($errors);
        $output->writeln('Errors found: ' . $errorsCount . "\n");

        foreach ($errors as $error) {
            $output->writeln('Error: ' . $error['error'], Output::VERBOSITY_VERBOSE);
            $output->writeln($error['file'] . ':' . $error['line'], Output::VERBOSITY_VERBOSE);
            if ($error['source'] !== $error['file']) {
                $output->writeln('Source: ' . $error['source'], Output::VERBOSITY_VERBOSE);
            }

            $contentRows = explode("\n", $error['content']);
            $contentRowsCount = count($contentRows);
            $newContentRows = [];

            $cipherCount = strlen((string)$contentRowsCount);
            for ($i = max(1,$error['line'] - 5); $i <= min($contentRowsCount, $error['line'] + 5); ++$i) {
                $newContentRows[] = str_pad((string)$i, $cipherCount, ' ', STR_PAD_LEFT) . ': ' . ($error['line'] === $i ? '<error>' . $contentRows[$i - 1] . '</error>' : $contentRows[$i - 1]);
            }

            $newContent = implode("\n", $newContentRows);
            $output->writeln("\nContent:\n" . $newContent, Output::VERBOSITY_VERY_VERBOSE);
            $output->writeln("\n", Output::VERBOSITY_VERBOSE);
        }
        return $errorsCount;
    }

    private function getLatteEngine(InputInterface $input): Engine
    {
        $bootstrap = $input->getOption('bootstrap');
        if ($bootstrap) {
            /** @var Container $container */
            $container = require $bootstrap;

            /** @var ITemplateFactory $templateFactory */
            $templateFactory = $container->getByType(ITemplateFactory::class);
            return $templateFactory->createTemplate()->getLatte();
        }
        return new Engine();
    }
}
