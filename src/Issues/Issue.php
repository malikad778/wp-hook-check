<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Issues;

readonly class Issue
{
    public function __construct(
        public string $type,
        public IssueSeverity $severity,
        public string $hookName,
        public string $file,
        public int $line,
        public string $message,
        public string $safeAlternative,
        public ?string $suggestion = null,
    ) {}

    public function typeLabel(): string
    {
        return strtoupper($this->type);
    }

    public function toArray(): array
    {
        return [
            'type'             => $this->type,
            'severity'         => $this->severity->value,
            'hook'             => $this->hookName,
            'file'             => $this->file,
            'line'             => $this->line,
            'message'          => $this->message,
            'safe_alternative' => $this->safeAlternative,
            'suggestion'       => $this->suggestion,
        ];
    }
}
