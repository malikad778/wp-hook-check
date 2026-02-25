<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Config;

use RuntimeException;

class ConfigLoader
{

    private const ALWAYS_EXCLUDE = ['vendor/'];

    public function load(string $configPath): Config
    {
        if (! file_exists($configPath)) {
            return new Config();
        }

        $raw = file_get_contents($configPath);

        if ($raw === false) {
            throw new RuntimeException("Cannot read config file: {$configPath}", 2);
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in config file '{$configPath}': " . json_last_error_msg(),
                2
            );
        }

        $defaults = new Config();

        $exclude = array_unique(array_merge(
            self::ALWAYS_EXCLUDE,
            $data['exclude'] ?? $defaults->exclude,
        ));

        $detectors = array_merge(
            $defaults->detectors,
            $data['detectors'] ?? [],
        );

        $ignore = $data['ignore'] ?? $defaults->ignore;

        $externalPrefixes = $data['external_prefixes'] ?? $defaults->externalPrefixes;

        return new Config(
            exclude: $exclude,
            detectors: $detectors,
            ignore: $ignore,
            externalPrefixes: $externalPrefixes,
        );
    }

    public function mergeCliOptions(
        Config $config,
        array $additionalExcludes = [],
        bool $ignoreDynamic = false,
    ): Config {
        $exclude = array_unique(array_merge($config->exclude, $additionalExcludes));

        $detectors = $config->detectors;
        if ($ignoreDynamic) {
            $detectors['dynamic_hook'] = false;
        }

        return new Config(
            exclude: $exclude,
            detectors: $detectors,
            ignore: $config->ignore,
            externalPrefixes: $config->externalPrefixes,
        );
    }
}
