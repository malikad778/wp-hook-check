<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Analyser;

use Adnan\WpHookAuditor\Config\Config;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileScanner
{

    public function scan(string $rootPath, Config $config): array
    {
        $finder = new Finder();
        $finder
            ->in($rootPath)
            ->files()
            ->name('*.php')
            ->followLinks();

        $finder->exclude('vendor');

        foreach ($config->exclude as $excludePath) {

            $cleaned = rtrim($excludePath, '/\\');
            if (is_dir($rootPath . DIRECTORY_SEPARATOR . $cleaned)) {
                $finder->exclude($cleaned);
            } else {
                $finder->notPath($cleaned);
            }
        }

        return iterator_to_array($finder, false);
    }
}
