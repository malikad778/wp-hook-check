<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\HookMap;

readonly class HookInvocation
{
    public function __construct(
        public string $hookName,
        public string $file,
        public int $line,
        public string $function,
        public bool $isDynamic,
    ) {}
}
