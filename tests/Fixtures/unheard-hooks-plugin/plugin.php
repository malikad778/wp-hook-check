<?php

declare(strict_types=1);

do_action('my_plugin_after_import', $results);

do_action('my_plugin_sync_complete', $count, $errors);

add_action('my_plugin_hooked_action', 'handle_hooked');
do_action('my_plugin_hooked_action');
