<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Analyser;

use Adnan\WpHookAuditor\HookMap\HookInvocation;
use Adnan\WpHookAuditor\HookMap\HookMap;
use Adnan\WpHookAuditor\HookMap\HookRegistration;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class HookNodeVisitor extends NodeVisitorAbstract
{

    private const REGISTER_FUNCTIONS = ['add_action', 'add_filter'];

    private const FIRE_FUNCTIONS = [
        'do_action',
        'do_action_ref_array',
        'apply_filters',
        'apply_filters_ref_array',
    ];

    private const REMOVE_FUNCTIONS = ['remove_action', 'remove_filter'];

    private const CHECK_FUNCTIONS = ['has_action', 'has_filter'];

    private const ALL_FUNCTIONS = [
        ...self::REGISTER_FUNCTIONS,
        ...self::FIRE_FUNCTIONS,
        ...self::REMOVE_FUNCTIONS,
        ...self::CHECK_FUNCTIONS,
    ];

    public function __construct(
        private readonly HookMap $hookMap,
        private readonly string $filePath,
    ) {}

    public function enterNode(Node $node): null|int|Node
    {
        if (! $node instanceof Node\Expr\FuncCall) {
            return null;
        }

        if (! $node->name instanceof Node\Name) {
            return null;
        }

        $functionName = $node->name->toString();

        if (! in_array($functionName, self::ALL_FUNCTIONS, true)) {
            return null;
        }

        $args = $node->args;

        if (empty($args)) {
            return null;
        }

        $hookArg  = $args[0];
        $hookExpr = $hookArg instanceof Node\Arg ? $hookArg->value : null;

        if ($hookExpr === null) {
            return null;
        }

        [$hookName, $isDynamic] = $this->resolveHookName($hookExpr);

        $line = $node->getStartLine();

        if (in_array($functionName, self::REGISTER_FUNCTIONS, true)) {
            $callback = $this->resolveCallback($args[1] ?? null);
            $priority = $this->resolvePriority($args[2] ?? null);

            $this->hookMap->addRegistration(new HookRegistration(
                hookName: $hookName,
                callback: $callback,
                priority: $priority,
                file: $this->filePath,
                line: $line,
                function: $functionName,
                isDynamic: $isDynamic,
            ));

            return null;
        }

        if (in_array($functionName, self::FIRE_FUNCTIONS, true)) {
            $this->hookMap->addInvocation(new HookInvocation(
                hookName: $hookName,
                file: $this->filePath,
                line: $line,
                function: $functionName,
                isDynamic: $isDynamic,
            ));

            return null;
        }

        if (in_array($functionName, self::REMOVE_FUNCTIONS, true)) {
            $callback = $this->resolveCallback($args[1] ?? null);

            $this->hookMap->addRegistration(new HookRegistration(
                hookName: $hookName,
                callback: $callback,
                priority: $this->resolvePriority($args[2] ?? null),
                file: $this->filePath,
                line: $line,
                function: $functionName,
                isDynamic: $isDynamic,
            ));

            return null;
        }

        if (in_array($functionName, self::CHECK_FUNCTIONS, true)) {
            $this->hookMap->addInvocation(new HookInvocation(
                hookName: $hookName,
                file: $this->filePath,
                line: $line,
                function: $functionName,
                isDynamic: $isDynamic,
            ));
        }

        return null;
    }

    private function resolveHookName(Node\Expr $expr): array
    {

        if ($expr instanceof Node\Scalar\String_) {
            return [$expr->value, false];
        }

        if ($expr instanceof Node\Expr\Variable) {
            $name = is_string($expr->name) ? ('$' . $expr->name) : '$variable';

            return [$name, true];
        }

        if ($expr instanceof Node\Scalar\InterpolatedString) {
            return ['dynamic', true];
        }

        if ($expr instanceof Node\Expr\BinaryOp\Concat) {

            if ($expr->left instanceof Node\Scalar\String_) {
                return [$expr->left->value . '*', true];
            }

            return ['dynamic', true];
        }

        return ['dynamic', true];
    }

    private function resolveCallback(Node\Arg|null $arg): string
    {
        if ($arg === null) {
            return '';
        }

        $expr = $arg->value;

        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\Array_ && count($expr->items) === 2) {
            $object = $expr->items[0]->value ?? null;
            $method = $expr->items[1]->value ?? null;

            $objectStr = match (true) {
                $object instanceof Node\Expr\Variable => '$' . (string) $object->name,
                $object instanceof Node\Scalar\String_ => $object->value,
                default => '?',
            };

            $methodStr = $method instanceof Node\Scalar\String_ ? $method->value : '?';

            $separator = str_starts_with($objectStr, '$') ? '->' : '::';

            return "{$objectStr}{$separator}{$methodStr}";
        }

        if ($expr instanceof Node\Expr\Closure || $expr instanceof Node\Expr\ArrowFunction) {
            return '{closure}';
        }

        if ($expr instanceof Node\Expr\Variable) {
            return '$' . (is_string($expr->name) ? $expr->name : 'callback');
        }

        return '{unknown}';
    }

    private function resolvePriority(Node\Arg|null $arg): int
    {
        if ($arg === null) {
            return 10;
        }

        $expr = $arg->value;

        if ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\UnaryMinus && $expr->expr instanceof Node\Scalar\LNumber) {
            return -$expr->expr->value;
        }

        return 10;
    }
}
