<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Commands;

use Adnan\WpHookAuditor\Analyser\HookAnalyser;
use Adnan\WpHookAuditor\Config\ConfigLoader;
use Adnan\WpHookAuditor\Reporters\ConsoleReporter;
use Adnan\WpHookAuditor\Reporters\GithubAnnotationReporter;
use Adnan\WpHookAuditor\Reporters\JsonReporter;
use Symfony\Component\Console\Output\ConsoleOutput;
use WP_CLI;

/**
 * Detect orphaned and unheard WordPress hooks via static analysis.
 *
 * ## OPTIONS
 *
 * [<path>]
 * : The directory or file to scan.
 * ---
 * default: .
 * ---
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * default: table
 * options:
 *   - table
 *   - json
 *   - github
 * ---
 *
 * [--fail-on=<severity>]
 * : Exit code 1 threshold.
 * ---
 * default: high
 * options:
 *   - high
 *   - medium
 *   - any
 *   - none
 * ---
 *
 * [--exclude=<paths>]
 * : Comma-separated paths to ignore. Relative to the search path.
 *
 * [--only=<detectors>]
 * : Comma-separated list of detectors to run.
 * ---
 * default: all
 * options:
 *   - orphaned
 *   - unheard
 *   - typo
 *   - dynamic
 *   - all
 * ---
 *
 * [--ignore-dynamic]
 * : Suppress INFO notices about dynamic hook names.
 *
 * [--config=<file>]
 * : Path to configuration file.
 * ---
 * default: wp-hook-audit.json
 * ---
 *
 * ## EXAMPLES
 *
 *     # Scan the current directory
 *     $ wp hook-check .
 *
 *     # Scan a specific plugin, ignoring vendor and node_modules
 *     $ wp hook-check ./wp-content/plugins/my-plugin --exclude=vendor,node_modules
 *
 *     # Scan with JSON output
 *     $ wp hook-check . --format=json
 *
 * @when before_wp_load
 */
class WpCliCommand
{
    public function __invoke(array $args, array $assoc_args): void
    {
        $path   = $args[0] ?? '.';
        $format = $assoc_args['format'] ?? 'table';

        $resolvedPath = $this->resolvePath($path);
        if ($resolvedPath === null) {
            WP_CLI::error(sprintf('Path "%s" does not exist or is not readable.', $path));
            return;
        }

        $configPath = $this->resolveConfigPath($resolvedPath, $assoc_args['config'] ?? 'wp-hook-audit.json');

        $configLoader = new ConfigLoader();
        $config       = $configLoader->load($configPath);

        // Apply CLI overrides
        if (! empty($assoc_args['exclude'])) {
            $cliExcludes     = array_map('trim', explode(',', $assoc_args['exclude']));
            $config->exclude = array_unique(array_merge($config->exclude, $cliExcludes));
        }

        if (isset($assoc_args['ignore-dynamic'])) {
            $config->detectors['dynamic_hook'] = false;
        }

        $only = $assoc_args['only'] ?? 'all';
        if ($only !== 'all') {
            $config = $this->applyOnlyFilter($config, $only);
        }

        $consoleOutput = new ConsoleOutput();
        $reporter      = match ($format) {
            'json'   => new JsonReporter($consoleOutput),
            'github' => new GithubAnnotationReporter($consoleOutput),
            default  => new ConsoleReporter($consoleOutput),
        };

        try {
            $analyser = new HookAnalyser();
            $result   = $analyser->analyse($resolvedPath, $config);

            foreach ($result->parseErrors as $parseError) {
                $consoleOutput->writeln("<comment>⚠ {$parseError}</comment>");
            }

            $reporter->report($result->issues, $result->fileCount, $result->duration);

            $exitCode = $this->calculateExitCode($result->issues, $assoc_args['fail-on'] ?? 'high');
            if ($exitCode > 0) {
                // Exit quietly so WP_CLI doesn't print its own generic error
                exit($exitCode);
            }

        } catch (\Throwable $e) {
            WP_CLI::error('Scan failed: ' . $e->getMessage());
        }
    }

    private function resolvePath(string $path): ?string
    {
        $realPath = realpath($path);
        return $realPath !== false ? $realPath : null;
    }

    private function resolveConfigPath(string $scanPath, string $configName): string
    {
        if (is_file($configName) && is_readable($configName)) {
            return realpath($configName);
        }

        $dir = is_dir($scanPath) ? $scanPath : dirname($scanPath);
        $potentialConfig = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $configName;

        if (is_file($potentialConfig) && is_readable($potentialConfig)) {
            return realpath($potentialConfig);
        }

        return $configName;
    }

    private function applyOnlyFilter($config, string $only)
    {
        $allowed = array_map('trim', explode(',', $only));
        $map     = [
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

        return new \Adnan\WpHookAuditor\Config\Config(
            exclude: $config->exclude,
            detectors: $newDetectors,
            ignore: $config->ignore,
            externalPrefixes: $config->externalPrefixes,
        );
    }

    private function calculateExitCode(array $issues, string $failOn): int
    {
        if ($failOn === 'none') {
            return 0;
        }

        $highestSeverityFound = 0; // 0=info, 1=medium, 2=high

        foreach ($issues as $issue) {
            $val = match ($issue->severity) {
                \Adnan\WpHookAuditor\Issues\IssueSeverity::HIGH => 2,
                \Adnan\WpHookAuditor\Issues\IssueSeverity::MEDIUM => 1,
                default => 0,
            };
            $highestSeverityFound = max($highestSeverityFound, $val);
        }

        $threshold = match ($failOn) {
            'high'   => 2,
            'medium' => 1,
            'any'    => 0,
            default  => 2,
        };

        if ($highestSeverityFound >= $threshold && count($issues) > 0) {
            return 1;
        }

        return 0;
    }
}
