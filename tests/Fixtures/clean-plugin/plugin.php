<?php

declare(strict_types=1);

add_action('my_plugin_init', 'my_plugin_boot');
add_filter('my_plugin_title', 'my_plugin_filter_title');

do_action('my_plugin_init');
apply_filters('my_plugin_title', get_the_title());
