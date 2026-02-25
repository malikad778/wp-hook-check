<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Reporters;

use Adnan\WpHookAuditor\Issues\Issue;
use Symfony\Component\Console\Output\OutputInterface;

class JsonReporter implements ReporterInterface
{
    public function __construct(private readonly OutputInterface $output) {}

    public function report(array $issues, int $fileCount, float $duration): void
    {
        $payload = [
            'meta' => [
                'files_scanned' => $fileCount,
                'duration_sec'  => $duration,
                'issue_count'   => count($issues),
            ],
            'issues' => array_map(fn (Issue $issue) => $issue->toArray(), $issues),
        ];

        $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
