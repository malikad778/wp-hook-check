<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Analyser;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\Detectors\DetectorInterface;
use Adnan\WpHookAuditor\Detectors\DynamicHookDetector;
use Adnan\WpHookAuditor\Detectors\OrphanedListenerDetector;
use Adnan\WpHookAuditor\Detectors\TypoDetector;
use Adnan\WpHookAuditor\Detectors\UnheardHookDetector;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\Issues\Issue;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

class AnalysisResult
{

    public function __construct(
        public readonly array $issues,
        public readonly int $fileCount,
        public readonly float $duration,
        public readonly HookMap $hookMap,
        public readonly array $parseErrors = [],
    ) {}
}

class HookAnalyser
{
    public function analyse(
        string $rootPath,
        Config $config,
        ?\Closure $onStart = null,
        ?\Closure $onProgress = null
    ): AnalysisResult {
        $startTime   = microtime(true);
        $hookMap     = new HookMap();
        $parseErrors = [];

        $scanner = new FileScanner();
        $files   = $scanner->scan($rootPath, $config);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        if ($onStart !== null) {
            $onStart(count($files));
        }

        foreach ($files as $file) {
            if ($onProgress !== null) {
                $onProgress();
            }
            $code = file_get_contents($file->getRealPath());

            if ($code === false) {
                $parseErrors[] = "Cannot read file: {$file->getRealPath()}";
                continue;
            }

            try {
                $ast        = $parser->parse($code) ?? [];
                $traverser  = new NodeTraverser();
                $visitor    = new HookNodeVisitor($hookMap, $file->getRealPath());
                $traverser->addVisitor($visitor);
                $traverser->traverse($ast);
            } catch (\PhpParser\Error $e) {
                $parseErrors[] = "Parse error in {$file->getRealPath()}: {$e->getMessage()}";
            }
        }

        $issues = [];
        foreach ($this->buildDetectors($config) as $detector) {
            $issues = array_merge($issues, $detector->detect($hookMap, $config));
        }

        return new AnalysisResult(
            issues: $issues,
            fileCount: count($files),
            duration: round(microtime(true) - $startTime, 3),
            hookMap: $hookMap,
            parseErrors: $parseErrors,
        );
    }

    private function buildDetectors(Config $config): array
    {
        $detectors = [];

        if ($config->isDetectorEnabled('orphaned_listener')) {
            $detectors[] = new OrphanedListenerDetector();
        }

        if ($config->isDetectorEnabled('unheard_hook')) {
            $detectors[] = new UnheardHookDetector();
        }

        if ($config->isDetectorEnabled('typo')) {
            $detectors[] = new TypoDetector();
        }

        if ($config->isDetectorEnabled('dynamic_hook')) {
            $detectors[] = new DynamicHookDetector();
        }

        return $detectors;
    }
}
