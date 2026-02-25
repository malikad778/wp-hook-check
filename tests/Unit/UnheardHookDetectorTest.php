<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Issues\IssueSeverity;

it('detects unheard do_action as MEDIUM', function () {
    $issues = analyse(<<<'PHP'
        do_action( 'my_plugin_after_sync', $results );
        PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->type)->toBe('unheard_hook')
        ->and($issues[0]->severity)->toBe(IssueSeverity::MEDIUM)
        ->and($issues[0]->hookName)->toBe('my_plugin_after_sync');
});

it('detects unheard apply_filters as MEDIUM', function () {
    $issues = analyse(<<<'PHP'
        apply_filters( 'my_plugin_widget_content', $content );
        PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->type)->toBe('unheard_hook')
        ->and($issues[0]->severity)->toBe(IssueSeverity::MEDIUM);
});

it('does NOT fire when add_action listener exists', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_after_sync', 'handle_sync' );
        do_action( 'my_plugin_after_sync', $results );
        PHP);

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toBeEmpty();
});

it('does NOT fire for external hook prefixes', function () {
    $issues = analyse(<<<'PHP'
        do_action( 'woocommerce_before_cart', $cart );
        PHP);

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toBeEmpty();
});

it('does NOT fire for ignored hooks', function () {
    $issues = analyse(
        <<<'PHP'
        do_action( 'my_plugin_extensibility_point' );
        PHP,
        ['ignore' => [['type' => 'unheard_hook', 'hook' => 'my_plugin_extensibility_point']]],
    );

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toBeEmpty();
});

it('does NOT fire for dynamic hook names', function () {
    $issues = analyse(<<<'PHP'
        $hook = 'my_dynamic_hook';
        do_action( $hook );
        PHP);

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toBeEmpty();
});

it('does NOT fire for has_action checks', function () {
    $issues = analyse(<<<'PHP'
        has_action( 'my_plugin_hook_check' );
        PHP);

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toBeEmpty();
});

it('reports only one issue per unheard hook name', function () {
    $issues = analyse(<<<'PHP'
        do_action( 'my_unheard_hook', $arg1 );
        do_action( 'my_unheard_hook', $arg2 );
        PHP);

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toHaveCount(1);
});
