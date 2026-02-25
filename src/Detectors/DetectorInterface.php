<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Detectors;

use Adnan\WpHookAuditor\Config\Config;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\Issues\Issue;

interface DetectorInterface
{

    public function detect(HookMap $hookMap, Config $config): array;
}
