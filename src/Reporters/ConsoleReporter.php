<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Reporters;

use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleReporter implements ReporterInterface
{
    private const DIVIDER = '──────────────────────────────────────────────────────────';

    public function __construct(private readonly OutputInterface $output) {}

    public function report(array $issues, int $fileCount, float $duration): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '  <options=bold;fg=white;bg=blue> WP HOOK AUDITOR </> Scanned <fg=cyan>%d</> files in <fg=cyan>%ss</>',
            $fileCount,
            $duration,
        ));
        $this->output->writeln('  ' . self::DIVIDER);
        $this->output->writeln('');

        if (empty($issues)) {
            $this->output->writeln('  <fg=green;options=bold>✓ No hook issues detected.</>');
            $this->output->writeln('');
            $this->output->writeln('  ' . self::DIVIDER);
            $this->printSummary($issues);

            return;
        }

        foreach ($issues as $issue) {
            $this->printIssue($issue);
        }

        $this->output->writeln('  ' . self::DIVIDER);
        $this->printSummary($issues);
    }

    private function printIssue(Issue $issue): void
    {
        $severityTag = match ($issue->severity) {
            IssueSeverity::HIGH   => '<fg=red;options=bold>',
            IssueSeverity::MEDIUM => '<fg=yellow;options=bold>',
            IssueSeverity::INFO   => '<fg=cyan;options=bold>',
        };

        $this->output->writeln(sprintf(
            "  {$severityTag}[%s] %s</>",
            $issue->severity->label(),
            $issue->typeLabel(),
        ));

        $this->output->writeln(sprintf(
            '  <fg=default>File  :</> %s<fg=default>:</>%d',
            $this->relativePath($issue->file),
            $issue->line,
        ));

        $this->output->writeln(sprintf(
            '  <fg=default>Hook  :</> <fg=yellow>%s</>',
            $issue->hookName,
        ));

        if ($issue->suggestion !== null) {
            $this->output->writeln(sprintf(
                '  <fg=default>Closest match:</> <fg=green>%s</>',
                $issue->suggestion,
            ));
        }

        $this->output->writeln('');
        $this->output->writeln('  ' . $issue->message);
        $this->output->writeln('');
        $this->output->writeln('  <fg=default>Fix:</> ' . $issue->safeAlternative);
        $this->output->writeln('');
        $this->output->writeln('  ' . self::DIVIDER);
        $this->output->writeln('');
    }

    private function printSummary(array $issues): void
    {
        $high   = count(array_filter($issues, fn ($i) => $i->severity === IssueSeverity::HIGH));
        $medium = count(array_filter($issues, fn ($i) => $i->severity === IssueSeverity::MEDIUM));
        $info   = count(array_filter($issues, fn ($i) => $i->severity === IssueSeverity::INFO));

        $highStr   = $high   > 0 ? "<fg=red;options=bold>{$high} HIGH</>"   : "<fg=default>{$high} HIGH</>";
        $mediumStr = $medium > 0 ? "<fg=yellow>{$medium} MEDIUM</>"        : "<fg=default>{$medium} MEDIUM</>";
        $infoStr   = $info   > 0 ? "<fg=cyan>{$info} INFO</>"              : "<fg=default>{$info} INFO</>";

        $this->output->writeln('');
        $this->output->writeln("  <options=bold>SUMMARY</>  {$highStr}   {$mediumStr}   {$infoStr}");
        $this->output->writeln('');
    }

    private function relativePath(string $absolutePath): string
    {
        $cwd = getcwd();
        if ($cwd && str_starts_with($absolutePath, $cwd)) {
            return ltrim(str_replace($cwd, '', $absolutePath), DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}
