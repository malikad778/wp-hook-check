<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Detectors;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\HookMap\HookRegistration;
use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;

class OrphanedListenerDetector implements DetectorInterface
{
    public function detect(HookMap $hookMap, Config $config): array
    {
        $issues = [];

        foreach ($hookMap->getRegistrations() as $hookName => $registrations) {
            foreach ($registrations as $registration) {

                if ($registration->isDynamic) {
                    continue;
                }

                if (in_array($registration->function, ['remove_action', 'remove_filter'], true)) {
                    continue;
                }

                if ($config->isExternalHook($hookName)) {
                    continue;
                }

                if ($config->isIgnored('orphaned_listener', $hookName)) {
                    continue;
                }

                if (! $hookMap->hasInvocation($hookName)) {
                    $fnType = str_contains($registration->function, 'action') ? 'action' : 'filter';

                    $issues[] = new Issue(
                        type: 'orphaned_listener',
                        severity: IssueSeverity::HIGH,
                        hookName: $hookName,
                        file: $registration->file,
                        line: $registration->line,
                        message: sprintf(
                            "%s('%s') registered (callback: %s) - no matching do_action() or apply_filters() found.",
                            $registration->function,
                            $hookName,
                            $registration->callback ?: '?',
                        ),
                        safeAlternative: sprintf(
                            "Either remove the add_%s() call or add do_%s('%s') where it should fire.",
                            $fnType,
                            $fnType,
                            $hookName,
                        ),
                    );

                    break;
                }
            }
        }

        return $issues;
    }
}
