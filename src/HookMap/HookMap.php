<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\HookMap;

class HookMap
{

    private array $registrations = [];

    private array $invocations = [];

    public function addRegistration(HookRegistration $r): void
    {
        $this->registrations[$r->hookName][] = $r;
    }

    public function addInvocation(HookInvocation $i): void
    {
        $this->invocations[$i->hookName][] = $i;
    }

    public function getRegistrations(): array
    {
        return $this->registrations;
    }

    public function getInvocations(): array
    {
        return $this->invocations;
    }

    public function hasInvocation(string $hookName): bool
    {
        return isset($this->invocations[$hookName]);
    }

    public function hasRegistration(string $hookName): bool
    {
        return isset($this->registrations[$hookName]);
    }

    public function getRegisteredHookNames(): array
    {
        return array_keys($this->registrations);
    }

    public function getInvokedHookNames(): array
    {
        return array_keys($this->invocations);
    }

    public function totalRegistrations(): int
    {
        return array_sum(array_map('count', $this->registrations));
    }

    public function totalInvocations(): int
    {
        return array_sum(array_map('count', $this->invocations));
    }
}
