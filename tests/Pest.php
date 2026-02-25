<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Analyser\HookAnalyser;
use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\Issues\Issue;

function analyse(string $phpSnippet, array $configOverrides = []): array
{

    $tmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wp_hook_' . uniqid();
    @mkdir($tmpDir, 0777, true);
    $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'snippet.php';
    file_put_contents($tmpFile, "<?php\n\n" . $phpSnippet);

    $defaultConfig = new Config();
    $config = new Config(
        exclude:          $configOverrides['exclude']          ?? $defaultConfig->exclude,
        detectors:        $configOverrides['detectors']        ?? $defaultConfig->detectors,
        ignore:           $configOverrides['ignore']           ?? $defaultConfig->ignore,
        externalPrefixes: $configOverrides['externalPrefixes'] ?? $defaultConfig->externalPrefixes,
    );

    $analyser = new HookAnalyser();
    $result   = $analyser->analyse($tmpDir, $config);

    @unlink($tmpFile);
    @rmdir($tmpDir);

    return $result->issues;
}

function analyseFixture(string $fixtureName, array $configOverrides = []): array
{
    $fixturePath = __DIR__ . '/Fixtures/' . $fixtureName;

    $defaultConfig = new Config();
    $config = new Config(
        exclude:          $configOverrides['exclude']          ?? [],  

        detectors:        $configOverrides['detectors']        ?? $defaultConfig->detectors,
        ignore:           $configOverrides['ignore']           ?? $defaultConfig->ignore,
        externalPrefixes: $configOverrides['externalPrefixes'] ?? $defaultConfig->externalPrefixes,
    );

    $analyser = new HookAnalyser();
    $result   = $analyser->analyse($fixturePath, $config);

    return $result->issues;
}
