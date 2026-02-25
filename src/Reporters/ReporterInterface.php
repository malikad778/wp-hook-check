<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Reporters;

use Adnan\WpHookAuditor\Issues\Issue;

interface ReporterInterface
{

    public function report(array $issues, int $fileCount, float $duration): void;
}
