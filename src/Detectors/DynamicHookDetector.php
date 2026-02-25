<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Detectors;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;

class DynamicHookDetector implements DetectorInterface
{
    public function detect(HookMap $hookMap, Config $config): array
    {
        $issues = [];

        foreach ($hookMap->getRegistrations() as $hookName => $registrations) {
            foreach ($registrations as $registration) {
                if (! $registration->isDynamic) {
                    continue;
                }

                $issues[] = new Issue(
                    type: 'dynamic_hook',
                    severity: IssueSeverity::INFO,
                    hookName: $hookName,
                    file: $registration->file,
                    line: $registration->line,
                    message: sprintf(
                        "%s() at line %d - hook name is a variable, can't be checked statically.",
                        $registration->function,
                        $registration->line,
                    ),
                    safeAlternative: 'Nothing to fix - informational only.',
                );
            }
        }

        foreach ($hookMap->getInvocations() as $hookName => $invocations) {
            foreach ($invocations as $invocation) {
                if (! $invocation->isDynamic) {
                    continue;
                }

                $issues[] = new Issue(
                    type: 'dynamic_hook',
                    severity: IssueSeverity::INFO,
                    hookName: $hookName,
                    file: $invocation->file,
                    line: $invocation->line,
                    message: sprintf(
                        "%s() at line %d -  hook name is a variable, can't be checked statically.",
                        $invocation->function,
                        $invocation->line,
                    ),
                    safeAlternative: 'Nothing to fix - informational only.',
                );
            }
        }

        return $issues;
    }
}
