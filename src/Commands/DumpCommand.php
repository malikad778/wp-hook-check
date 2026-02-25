<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Commands;

use Adnan\WpHookAuditor\Analyser\HookAnalyser;
use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\Config\ConfigLoader;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends Command
{
    protected static string $defaultName = 'dump';

    protected function configure(): void
    {
        $this
            ->setName('dump')
            ->setDescription('Dump the full hook map without running any detectors.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Directory to scan', '.')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: table, json', 'table')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'wp-hook-audit.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path       = (string) $input->getArgument('path');
        $format     = (string) $input->getOption('format');
        $configFile = (string) $input->getOption('config');

        $scanPath = $path === '.' ? (string) getcwd() : (realpath($path) ?: $path);

        try {
            $loader = new ConfigLoader();
            $config = $loader->load($configFile !== 'wp-hook-audit.json' ? $configFile : $scanPath . '/wp-hook-audit.json');
        } catch (RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return 2;
        }

        $config = new Config(
            exclude: $config->exclude,
            detectors: array_fill_keys(array_keys($config->detectors), false),
            ignore: $config->ignore,
            externalPrefixes: $config->externalPrefixes,
        );

        try {
            $analyser = new HookAnalyser();
            $result   = $analyser->analyse($scanPath, $config);
        } catch (RuntimeException $e) {
            $output->writeln("<error>Analysis error: {$e->getMessage()}</error>");

            return 2;
        }

        $hookMap = $result->hookMap;

        if ($format === 'json') {
            $data = [
                'registrations' => [],
                'invocations'   => [],
            ];

            foreach ($hookMap->getRegistrations() as $hookName => $regs) {
                foreach ($regs as $r) {
                    $data['registrations'][] = [
                        'hook'       => $hookName,
                        'function'   => $r->function,
                        'callback'   => $r->callback,
                        'priority'   => $r->priority,
                        'file'       => $r->file,
                        'line'       => $r->line,
                        'is_dynamic' => $r->isDynamic,
                    ];
                }
            }

            foreach ($hookMap->getInvocations() as $hookName => $invs) {
                foreach ($invs as $i) {
                    $data['invocations'][] = [
                        'hook'       => $hookName,
                        'function'   => $i->function,
                        'file'       => $i->file,
                        'line'       => $i->line,
                        'is_dynamic' => $i->isDynamic,
                    ];
                }
            }

            $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '  <options=bold;fg=white;bg=blue> WP HOOK AUDITOR DUMP </>  Scanned <fg=cyan>%d</> files in <fg=cyan>%ss</>',
            $result->fileCount,
            $result->duration,
        ));
        $output->writeln('');
        $output->writeln('  <options=bold>REGISTRATIONS</> (add_action / add_filter)');

        if ($hookMap->totalRegistrations() === 0) {
            $output->writeln('  <fg=gray>  No registrations found.</fg=gray>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Hook Name', 'Function', 'Callback', 'Pri', 'File:Line']);
            $table->setStyle('box');

            foreach ($hookMap->getRegistrations() as $hookName => $regs) {
                foreach ($regs as $r) {
                    $table->addRow([
                        $r->isDynamic ? "<fg=yellow>{$hookName}</>" : $hookName,
                        $r->function,
                        $r->callback,
                        $r->priority,
                        $this->shortenPath($r->file) . ':' . $r->line,
                    ]);
                }
            }

            $table->render();
        }

        $output->writeln('');
        $output->writeln('  <options=bold>INVOCATIONS</> (do_action / apply_filters)');

        if ($hookMap->totalInvocations() === 0) {
            $output->writeln('  <fg=gray>  No invocations found.</fg=gray>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Hook Name', 'Function', 'File:Line']);
            $table->setStyle('box');

            foreach ($hookMap->getInvocations() as $hookName => $invs) {
                foreach ($invs as $i) {
                    $table->addRow([
                        $i->isDynamic ? "<fg=yellow>{$hookName}</>" : $hookName,
                        $i->function,
                        $this->shortenPath($i->file) . ':' . $i->line,
                    ]);
                }
            }

            $table->render();
        }

        $output->writeln('');

        return Command::SUCCESS;
    }

    private function shortenPath(string $path): string
    {
        $cwd = getcwd();
        if ($cwd && str_starts_with($path, $cwd)) {
            return ltrim(str_replace($cwd, '', $path), DIRECTORY_SEPARATOR);
        }

        return basename(dirname($path)) . '/' . basename($path);
    }
}
