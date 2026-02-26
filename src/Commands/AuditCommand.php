<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Commands;

use Adnan\WpHookAuditor\Analyser\HookAnalyser;
use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\Config\ConfigLoader;
use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;
use Adnan\WpHookAuditor\Reporters\ConsoleReporter;
use Adnan\WpHookAuditor\Reporters\GithubAnnotationReporter;
use Adnan\WpHookAuditor\Reporters\JsonReporter;
use Adnan\WpHookAuditor\Reporters\ReporterInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuditCommand extends Command
{
    protected static string $defaultName = 'audit';

    protected function configure(): void
    {
        $this
            ->setName('audit')
            ->setDescription('Scan a WordPress codebase for orphaned and unheard hooks.')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Directory to scan (defaults to current working directory)',
                '.',
            )
            ->addOption('format',         'f', InputOption::VALUE_REQUIRED, 'Output format: table, json, github',      'table')
            ->addOption('fail-on',        null, InputOption::VALUE_REQUIRED, 'Exit 1 threshold: high, medium, any, none', 'high')
            ->addOption('exclude',        'e', InputOption::VALUE_REQUIRED, 'Comma-separated paths to exclude',        '')
            ->addOption('ignore-dynamic', null, InputOption::VALUE_NONE,     'Suppress INFO dynamic hook notices')
            ->addOption('only',           null, InputOption::VALUE_REQUIRED, 'Run only specific detectors: orphaned,unheard,typo,dynamic', '')
            ->addOption('config',         'c', InputOption::VALUE_REQUIRED, 'Path to config file',                    'wp-hook-audit.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path          = $this->resolvePath((string) $input->getArgument('path'));
        $format        = (string) $input->getOption('format');
        $failOn        = (string) $input->getOption('fail-on');
        $excludeRaw    = (string) $input->getOption('exclude');
        $ignoreDynamic = (bool)   $input->getOption('ignore-dynamic');
        $only          = (string) $input->getOption('only');
        $configFile    = (string) $input->getOption('config');

        $configPath = $this->resolveConfigPath($configFile, $path);

        try {
            $loader = new ConfigLoader();
            $config = $loader->load($configPath);
        } catch (RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return 2;
        }

        $additionalExcludes = $excludeRaw ? array_map('trim', explode(',', $excludeRaw)) : [];
        $config = (new ConfigLoader())->mergeCliOptions($config, $additionalExcludes, $ignoreDynamic);

        if ($only !== '') {
            $config = $this->applyOnlyFilter($config, $only);
        }

        if ($format === 'table') {
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output);
            // Example output: "   14/23 [=>--------------------------]  60% • 1 sec"
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% • %elapsed:6s%');
        } else {
            $progressBar = null;
        }

        try {
            $analyser = new HookAnalyser();
            $result   = $analyser->analyse(
                $path,
                $config,
                function (int $totalFiles) use ($progressBar) {
                    if ($progressBar) {
                        $progressBar->start($totalFiles);
                    }
                },
                function () use ($progressBar) {
                    if ($progressBar) {
                        $progressBar->advance();
                    }
                }
            );

            if ($progressBar) {
                $progressBar->finish();
                $output->writeln(''); // Add blank line after the progress bar finishes
                $output->writeln('');
            }
        } catch (RuntimeException $e) {
            $output->writeln("<error>Analysis error: {$e->getMessage()}</error>");

            return 2;
        }

        foreach ($result->parseErrors as $parseError) {
            $output->writeln("<comment>⚠ {$parseError}</comment>");
        }

        $reporter = $this->buildReporter($format, $output);
        $reporter->report($result->issues, $result->fileCount, $result->duration);

        return $this->resolveExitCode($result->issues, $failOn);
    }

    private function resolvePath(string $path): string
    {
        if ($path === '.') {
            return (string) getcwd();
        }

        return realpath($path) ?: $path;
    }

    private function resolveConfigPath(string $configFile, string $scanPath): string
    {
        if ($configFile !== 'wp-hook-audit.json') {

            return $configFile;
        }

        $inScanPath = rtrim($scanPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wp-hook-audit.json';
        if (file_exists($inScanPath)) {
            return $inScanPath;
        }

        return (string) getcwd() . DIRECTORY_SEPARATOR . 'wp-hook-audit.json';
    }

    private function applyOnlyFilter(Config $config, string $only): Config
    {
        $allowed     = array_map('trim', explode(',', $only));
        $map         = [
            'orphaned' => 'orphaned_listener',
            'unheard'  => 'unheard_hook',
            'typo'     => 'typo',
            'dynamic'  => 'dynamic_hook',
        ];
        $newDetectors = array_fill_keys(array_keys($config->detectors), false);

        foreach ($allowed as $key) {
            $internalKey = $map[$key] ?? $key;
            if (array_key_exists($internalKey, $newDetectors)) {
                $newDetectors[$internalKey] = true;
            }
        }

        return new Config(
            exclude: $config->exclude,
            detectors: $newDetectors,
            ignore: $config->ignore,
            externalPrefixes: $config->externalPrefixes,
        );
    }

    private function buildReporter(string $format, OutputInterface $output): ReporterInterface
    {
        return match ($format) {
            'json'   => new JsonReporter($output),
            'github' => new GithubAnnotationReporter($output),
            default  => new ConsoleReporter($output),
        };
    }

    private function resolveExitCode(array $issues, string $failOn): int
    {
        if ($failOn === 'none') {
            return Command::SUCCESS;
        }

        foreach ($issues as $issue) {
            $isHigh   = $issue->severity === IssueSeverity::HIGH;
            $isMedium = $issue->severity === IssueSeverity::MEDIUM;

            if ($failOn === 'high'   && $isHigh) return Command::FAILURE;
            if ($failOn === 'medium' && ($isHigh || $isMedium)) return Command::FAILURE;
            if ($failOn === 'any') return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
