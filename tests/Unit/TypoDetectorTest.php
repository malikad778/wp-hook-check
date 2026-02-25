<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Issues\IssueSeverity;

it('detects a Levenshtein distance-1 typo as HIGH', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_user_registerd', 'send_welcome' );
        do_action( 'my_plugin_user_registered', $user_id );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toHaveCount(1);

    $typo = array_values($typos)[0];
    expect($typo->severity)->toBe(IssueSeverity::HIGH)
        ->and($typo->hookName)->toBe('my_plugin_user_registerd')
        ->and($typo->suggestion)->toBe('my_plugin_user_registered');
});

it('detects a Levenshtein distance-2 typo as HIGH', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_chekout', 'handle_checkout' );
        do_action( 'my_plugin_checkout', $order );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toHaveCount(1);
});

it('does NOT fire on Levenshtein distance >= 3', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_foo', 'some_callback' );
        do_action( 'my_plugin_foobar', $arg );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toBeEmpty();
});

it('does NOT fire when hook names match exactly', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_exact_hook', 'callback' );
        do_action( 'my_exact_hook' );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toBeEmpty();
});

it('populates the suggestion field with the closest candidate', function () {
    $issues = analyse(<<<'PHP'
        add_action( 'my_plugin_usr_login', 'on_login' );
        do_action( 'my_plugin_user_login', $user );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->not->toBeEmpty();

    $typo = array_values($typos)[0];
    expect($typo->suggestion)->toBe('my_plugin_user_login');
});

it('does NOT fire on dynamic hook names', function () {
    $issues = analyse(<<<'PHP'
        $hook = 'my_dynamic';
        add_action( $hook, 'callback' );
        do_action( 'my_dynamik' );
        PHP);

    $typos = array_filter($issues, fn ($i) => $i->type === 'hook_name_typo');
    expect($typos)->toBeEmpty();
});
