<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Detectors;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\Issues\Issue;
use Adnan\WpHookAuditor\Issues\IssueSeverity;

class TypoDetector implements DetectorInterface
{

    private const MAX_DISTANCE = 2;

    public function detect(HookMap $hookMap, Config $config): array
    {
        $issues = [];

        $registeredNames = $hookMap->getRegisteredHookNames();
        $invokedNames    = $hookMap->getInvokedHookNames();

        foreach ($registeredNames as $hookName) {

            if ($hookMap->hasInvocation($hookName)) {
                continue;
            }

            $registrations = $hookMap->getRegistrations()[$hookName] ?? [];
            $allDynamic    = array_filter($registrations, fn ($r) => ! $r->isDynamic) === [];
            if ($allDynamic) {
                continue;
            }

            if ($config->isExternalHook($hookName)) {
                continue;
            }

            $bestMatch    = null;
            $bestDistance = PHP_INT_MAX;

            foreach ($invokedNames as $candidate) {
                if ($hookName === $candidate) {
                    continue;
                }

                $distance = levenshtein($hookName, $candidate);

                if ($distance >= 1 && $distance <= self::MAX_DISTANCE && $distance < $bestDistance) {
                    $bestMatch    = $candidate;
                    $bestDistance = $distance;
                }
            }

            if ($bestMatch !== null) {

                $staticRegistration = null;
                foreach ($registrations as $r) {
                    if (! $r->isDynamic) {
                        $staticRegistration = $r;
                        break;
                    }
                }

                if ($staticRegistration === null) {
                    continue;
                }

                $issues[] = new Issue(
                    type: 'hook_name_typo',
                    severity: IssueSeverity::HIGH,
                    hookName: $hookName,
                    file: $staticRegistration->file,
                    line: $staticRegistration->line,
                    message: sprintf(
                        "'%s' looks like a typo of '%s' (distance: %d) - '%s' will never run.",
                        $hookName,
                        $bestMatch,
                        $bestDistance,
                        $staticRegistration->callback,
                    ),
                    safeAlternative: sprintf(
                        "Change '%s' to '%s' to match the do_action() call.",
                        $hookName,
                        $bestMatch,
                    ),
                    suggestion: $bestMatch,
                );
            }
        }

        return $issues;
    }
}
