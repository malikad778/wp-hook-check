<?php

declare(strict_types=1);

$post_type = get_post_type() ?: 'post';

do_action('my_plugin_' . $post_type . '_saved', $post_id ?? 0);

add_action("my_plugin_{$post_type}_deleted", 'handle_post_delete');
