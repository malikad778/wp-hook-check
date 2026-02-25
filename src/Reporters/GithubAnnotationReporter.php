<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Reporters;

use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;
use Symfony\Component\Console\Output\OutputInterface;

class GithubAnnotationReporter implements ReporterInterface
{
    public function __construct(private readonly OutputInterface $output) {}

    public function report(array $issues, int $fileCount, float $duration): void
    {
        foreach ($issues as $issue) {
            $level   = $this->annotationLevel($issue->severity);
            $title   = strtoupper($issue->type);
            $message = str_replace(["\n", "\r"], ' ', $issue->message);

            $this->output->writeln(sprintf(
                '::%s file=%s,line=%d,title=%s::%s',
                $level,
                $issue->file,
                $issue->line,
                $title,
                $message,
            ));
        }

        $this->output->writeln(sprintf(
            '::notice title=WP Hook Auditor::Scanned %d files in %ss. Found %d issue(s).',
            $fileCount,
            $duration,
            count($issues),
        ));
    }

    private function annotationLevel(IssueSeverity $severity): string
    {
        return match ($severity) {
            IssueSeverity::HIGH   => 'error',
            IssueSeverity::MEDIUM => 'warning',
            IssueSeverity::INFO   => 'notice',
        };
    }
}
