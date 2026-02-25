<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Detectors;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;

class UnheardHookDetector implements DetectorInterface
{
    public function detect(HookMap $hookMap, Config $config): array
    {
        $issues = [];

        foreach ($hookMap->getInvocations() as $hookName => $invocations) {
            foreach ($invocations as $invocation) {

                if ($invocation->isDynamic) {
                    continue;
                }

                if (in_array($invocation->function, ['has_action', 'has_filter'], true)) {
                    continue;
                }

                if ($config->isExternalHook($hookName)) {
                    continue;
                }

                if ($config->isIgnored('unheard_hook', $hookName)) {
                    continue;
                }

                if (! $hookMap->hasRegistration($hookName)) {
                    $fnType = str_contains($invocation->function, 'action') ? 'action' : 'filter';

                    $issues[] = new Issue(
                        type: 'unheard_hook',
                        severity: IssueSeverity::MEDIUM,
                        hookName: $hookName,
                        file: $invocation->file,
                        line: $invocation->line,
                        message: sprintf(
                            "%s('%s') fired but no add_action() or add_filter() for '%s' found anywhere.",
                            $invocation->function,
                            $hookName,
                            $hookName,
                        ),
                        safeAlternative: sprintf(
                            "Add add_%s('%s', \$callback) where needed, or add it to external_prefixes if it's a WP core hook.",
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
