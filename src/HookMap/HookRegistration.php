<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\HookMap;

readonly class HookRegistration
{
    public function __construct(
        public string $hookName,
        public string $callback,
        public int $priority,
        public string $file,
        public int $line,
        public string $function,
        public bool $isDynamic,
    ) {}
}
