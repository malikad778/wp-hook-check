<?php

declare(strict_types=1);

use Adnan\WpHookAuditor\Issues\IssueSeverity;

it('does NOT fire when dynamic_hook detector is disabled (default config)', function () {
    $issues = analyse(<<<'PHP'
        $hook = 'my_dynamic_hook';
        add_action($hook, 'callback');
        do_action($hook);
        PHP);

    $dynamic = array_filter($issues, fn ($i) => $i->type === 'dynamic_hook');
    expect($dynamic)->toBeEmpty();
});

it('fires INFO for variable hook name in add_action when detector is enabled', function () {
    $issues = analyse(
        <<<'PHP'
        $hook = 'my_dynamic';
        add_action($hook, 'some_callback');
        PHP,
        ['detectors' => [
            'orphaned_listener' => false,
            'unheard_hook'      => false,
            'typo'              => false,
            'dynamic_hook'      => true,
        ]],
    );

    $dynamic = array_filter($issues, fn ($i) => $i->type === 'dynamic_hook');
    expect($dynamic)->not->toBeEmpty();
    expect(array_values($dynamic)[0]->severity)->toBe(IssueSeverity::INFO);
});

it('fires INFO for concatenated hook name in do_action when enabled', function () {
    $issues = analyse(
        <<<'PHP'
        $type = 'post';
        do_action('my_plugin_' . $type . '_saved', $id);
        PHP,
        ['detectors' => [
            'orphaned_listener' => false,
            'unheard_hook'      => false,
            'typo'              => false,
            'dynamic_hook'      => true,
        ]],
    );

    $dynamic = array_filter($issues, fn ($i) => $i->type === 'dynamic_hook');
    expect($dynamic)->not->toBeEmpty();
});

it('does NOT fire for string literal hook names', function () {
    $issues = analyse(
        <<<'PHP'
        add_action('my_static_hook', 'callback');
        do_action('my_static_hook');
        PHP,
        ['detectors' => [
            'orphaned_listener' => false,
            'unheard_hook'      => false,
            'typo'              => false,
            'dynamic_hook'      => true,
        ]],
    );

    $dynamic = array_filter($issues, fn ($i) => $i->type === 'dynamic_hook');
    expect($dynamic)->toBeEmpty();
});
