<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Issues\IssueSeverity;

it('clean-plugin fixture: 0 issues, all types', function () {
    $issues = analyseFixture('clean-plugin');
    expect($issues)->toBeEmpty();
});

it('orphaned-hooks-plugin fixture: 2 HIGH ORPHANED_LISTENER issues', function () {
    $issues = analyseFixture('orphaned-hooks-plugin');

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toHaveCount(2);

    foreach ($orphaned as $issue) {
        expect($issue->severity)->toBe(IssueSeverity::HIGH);
    }
});

it('unheard-hooks-plugin fixture: 2 MEDIUM UNHEARD_HOOK issues', function () {
    $issues = analyseFixture('unheard-hooks-plugin');

    $unheard = array_filter($issues, fn ($i) => $i->type === 'unheard_hook');
    expect($unheard)->toHaveCount(2);

    foreach ($unheard as $issue) {
        expect($issue->severity)->toBe(IssueSeverity::MEDIUM);
    }
});

it('typo-hooks-plugin fixture: 1 HIGH HOOK_NAME_TYPO issue', function () {
    $issues = analyseFixture('typo-hooks-plugin');

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toHaveCount(1);

    $typo = array_values($typos)[0];
    expect($typo->severity)->toBe(IssueSeverity::HIGH)
        ->and($typo->suggestion)->toBe('my_plugin_user_registered');
});

it('dynamic-hooks-plugin fixture: 0 issues with default config', function () {
    $issues = analyseFixture('dynamic-hooks-plugin');
    expect($issues)->toBeEmpty();
});

it('dynamic-hooks-plugin fixture: INFO issues when dynamic_hook is enabled', function () {
    $issues = analyseFixture('dynamic-hooks-plugin', [
        'detectors' => [
            'orphaned_listener' => false,
            'unheard_hook'      => false,
            'typo'              => false,
            'dynamic_hook'      => true,
        ],
    ]);

    $dynamic = array_filter($issues, fn ($i) => $i->type === 'dynamic_hook');
    expect($dynamic)->not->toBeEmpty();

    foreach ($dynamic as $issue) {
        expect($issue->severity)->toBe(IssueSeverity::INFO);
    }
});

it('woocommerce_ prefixed hook does not fire ORPHANED_LISTENER', function () {
    $issues = analyse(<<<'PHP'
        add_action('woocommerce_checkout_order_created', 'handle_order');
        add_filter('woocommerce_cart_contents', 'filter_cart');
        PHP);

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('hooks matched across two files do not fire UNHEARD or ORPHANED', function () {
    $issues = analyse(<<<'PHP'
        add_action('my_cross_file_hook', 'my_handler');
        do_action('my_cross_file_hook', $arg);
        PHP);

    $relevant = array_filter($issues, fn ($i) => in_array($i->type, ['orphaned_listener', 'unheard_hook'], true));
    expect($relevant)->toBeEmpty();
});

it('ignored hook/type pair produces 0 issues', function () {
    $issues = analyse(
        <<<'PHP'
        add_action('my_plugin_ext_point', 'noop');
        PHP,
        ['ignore' => [['type' => 'orphaned_listener', 'hook' => 'my_plugin_ext_point']]],
    );

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('disabling orphaned_listener detector suppresses HIGH issues', function () {
    $issues = analyse(
        <<<'PHP'
        add_action('my_orphaned_hook', 'some_callback');
        PHP,
        ['detectors' => [
            'orphaned_listener' => false,
            'unheard_hook'      => true,
            'typo'              => true,
            'dynamic_hook'      => false,
        ]],
    );

    $orphaned = array_filter($issues, fn ($i) => $i->type === 'orphaned_listener');
    expect($orphaned)->toBeEmpty();
});

it('ORPHANED_LISTENER issue has correct shape', function () {
    $issues = analyse(<<<'PHP'
        add_action('my_test_hook', 'my_callback');
        PHP);

    $issue = array_values(array_filter($issues, fn ($i) => $i->type === 'orphaned_listener'))[0] ?? null;

    expect($issue)->not->toBeNull()
        ->and($issue->type)->toBe('orphaned_listener')
        ->and($issue->severity)->toBe(IssueSeverity::HIGH)
        ->and($issue->hookName)->toBe('my_test_hook')
        ->and($issue->file)->toBeString()
        ->and($issue->line)->toBeInt()
        ->and($issue->message)->toBeString()
        ->and($issue->safeAlternative)->toBeString()
        ->and($issue->suggestion)->toBeNull();
});

it('HOOK_NAME_TYPO issue has suggestion field populated', function () {
    $issues = analyse(<<<'PHP'
        add_action('my_plugin_unt', 'callback');
        do_action('my_plugin_unit', $data);
        PHP);

    $typo = array_values(array_filter($issues, fn ($i) => $i->type === 'hook_name_typo'))[0] ?? null;

    expect($typo)->not->toBeNull()
        ->and($typo->suggestion)->not->toBeNull();
});
