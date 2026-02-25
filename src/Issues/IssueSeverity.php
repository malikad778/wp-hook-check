<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Issues;

enum IssueSeverity: string
{
    case HIGH   = 'high';
    case MEDIUM = 'medium';
    case INFO   = 'info';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function isAtLeast(self $other): bool
    {
        $order = [self::HIGH->value => 0, self::MEDIUM->value => 1, self::INFO->value => 2];

        return $order[$this->value] <= $order[$other->value];
    }
}
