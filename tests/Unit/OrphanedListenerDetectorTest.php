<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Issues\IssueSeverity;

it('detects orphaned add_action as HIGH', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_checkout_complete', 'send_confirmation' );
        PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->type)->toBe('orphaned_listener')
        ->and($issues[0]->severity)->toBe(IssueSeverity::HIGH)
        ->and($issues[0]->hookName)->toBe('my_checkout_complete');
});

it('detects orphaned add_filter as HIGH', function () {
    $issues = analyse(<<<'PHP'
        add_filter( 'my_custom_title', 'my_filter_callback' );
        PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->type)->toBe('orphaned_listener')
        ->and($issues[0]->severity)->toBe(IssueSeverity::HIGH);
});

it('does NOT fire when matching do_action exists', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_ready', 'boot_plugin' );
        do_action( 'my_plugin_ready' );
        PHP);

    expect($issues)->toBeEmpty();
});

it('does NOT fire when matching apply_filters exists', function () {
    $issues = analyse(<<<'PHP'
        add_filter( 'my_plugin_content', 'filter_content' );
        apply_filters( 'my_plugin_content', 'value' );
        PHP);

    expect($issues)->toBeEmpty();
});

it('does NOT fire for hooks starting with an external prefix (woocommerce_)', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'woocommerce_checkout_order_created', 'handle_order' );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('does NOT fire for WordPress core prefix wp_', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'wp_head', 'output_styles' );
        add_action( 'wp_enqueue_scripts', 'load_scripts' );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('does NOT fire for admin_ prefixed hooks', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'admin_menu', 'register_menu_page' );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('does NOT fire for hooks in the ignore list', function () {
    $issues = analyse(
        <<<'PHP'
        add_action( 'my_plugin_checkout', 'fn_callback' );
        PHP,
        ['ignore' => [['type' => 'orphaned_listener', 'hook' => 'my_plugin_checkout']]],
    );

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('does NOT fire for dynamic hook names', function () {
    $issues = analyse(<<<'PHP'
        $hook = 'my_plugin_init';
        add_action( $hook, 'my_callback' );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('does NOT fire for remove_action calls', function () {
    $issues = analyse(<<<'PHP'
        remove_action( 'my_plugin_init', 'some_callback' );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('returns only one issue per orphaned hook name regardless of listener count', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_orphaned_hook', 'callback_one' );
        add_action( 'my_orphaned_hook', 'callback_two', 20 );
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toHaveCount(1);
});
